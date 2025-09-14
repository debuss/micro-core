<?php

namespace Core\Strategy;

use Laminas\Diactoros\Response;
use League\Route\Http;
use League\Route\Http\Exception\{BadRequestException,
    ConflictException,
    ExpectationFailedException,
    ForbiddenException,
    GoneException,
    ImATeapotException,
    LengthRequiredException,
    MethodNotAllowedException,
    NotFoundException,
    PreconditionFailedException,
    PreconditionRequiredException,
    TooManyRequestsException,
    UnauthorizedException,
    UnavailableForLegalReasonsException,
    UnsupportedMediaException};
use League\Route\Route;
use League\Route\Strategy\JsonStrategy;
use ProblemDetails\{ProblemDetails, ProblemDetailsException};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;
use ReflectionMethod;
use Psr\Http\Message\{ResponseFactoryInterface, ResponseInterface, ServerRequestInterface};
use Throwable;
use function Laminas\Stratigility\middleware;
use function get_class, property_exists;

class ProblemDetailsStrategy extends JsonStrategy
{

    public function __construct(ResponseFactoryInterface $response)
    {
        parent::__construct(
            $response,
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     * @throws NotFoundExceptionInterface
     */
    public function invokeRouteCallable(Route $route, ServerRequestInterface $request): ResponseInterface
    {
        $controller = $route->getCallable($this->getContainer());

        $parameters = [];
        if (is_array($controller) && is_object($controller[0])) {
            $method = new ReflectionMethod($controller[0], $controller[1]);
            foreach ($method->getParameters() as $param) {
                if ($param->hasType() && $param->getType()->getName() === ServerRequestInterface::class) {
                    $parameters[] = $request;
                } elseif ($param->hasType() && $param->getType()->isBuiltin()) {
                    $parameters[] = $request->getAttribute(
                        $param->getName(),
                        $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null
                    );
                } elseif ($param->hasType() && $this->getContainer()->has($param->getType()->getName())) {
                    $parameters[] = $this->getContainer()->get($param->getType()->getName());
                } elseif ($param->hasType() && isset($request->getAttributes()[$param->getName()])) {
                    $parameters[] = $request->getAttribute($param->getName());
                } elseif (!$param->hasType() && isset($request->getAttributes()[$param->getName()])) {
                    $parameters[] = $request->getAttribute($param->getName());
                } elseif ($param->isDefaultValueAvailable()) {
                    $parameters[] = $param->getDefaultValue();
                } else {
                    $parameters[] = $param->getName();
                }
            }
        }

        $response = call_user_func_array($controller, $parameters);

        if ($this->isJsonSerializable($response)) {
            $body = json_encode($response, $this->jsonFlags);
            $response = $this->responseFactory->createResponse();
            $response->getBody()->write($body);
        }

        return $this->decorateResponse($response);
    }

    public function getThrowableHandler(): MiddlewareInterface
    {
        return middleware(function (ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
            try {
                return $handler->handle($request);
            } catch (Throwable $exception) {
                if ($exception instanceof ProblemDetailsException) {
                    throw $exception;
                }

                throw new ProblemDetailsException(
                    $this->getProblemDetailsFromThrowable($exception, $request)
                );
            }
        });
    }

    protected function buildJsonResponseMiddleware(Http\Exception $exception): MiddlewareInterface
    {
        return middleware(
            fn(ServerRequestInterface $request): ResponseInterface => throw new ProblemDetailsException(
                $this->getProblemDetailsFromThrowable($exception, $request)
            )
        );
    }

    protected function getProblemDetailsFromThrowable(Throwable $exception, ServerRequestInterface $request): ProblemDetails
    {
        $status = match (get_class($exception)) {
            BadRequestException::class => 400,
            UnauthorizedException::class => 401,
            ForbiddenException::class => 403,
            NotFoundException::class => 404,
            MethodNotAllowedException::class => 405,
            ConflictException::class => 409,
            GoneException::class => 410,
            LengthRequiredException::class => 411,
            PreconditionFailedException::class => 412,
            UnsupportedMediaException::class => 415,
            ExpectationFailedException::class => 417,
            ImATeapotException::class => 418,
            PreconditionRequiredException::class => 428,
            TooManyRequestsException::class => 429,
            UnavailableForLegalReasonsException::class => 451,
            default => $exception->getCode() >= 400 && $exception->getCode() < 600
                ? $exception->getCode()
                : 500
        };

        $detail = property_exists($exception, 'detail')
            ? $exception->detail
            : match ($status) {
                400 => 'The request could not be understood by the server due to malformed syntax.',
                401 => 'The request requires user authentication.',
                403 => 'The server understood the request, but is refusing to fulfill it.',
                404 => 'The requested resource was not found.',
                405 => 'The method is not allowed for the requested URL.',
                409 => 'The request could not be completed due to a conflict with the current state of the resource.',
                410 => 'The requested resource is no longer available at the server and no forwarding address is known.',
                411 => 'The server refuses to accept the request without a defined Content-Length.',
                412 => 'The precondition given in one or more of the request-header fields evaluated to false when it was tested on the server.',
                415 => 'The server is refusing to service the request because the payload is in a format not supported by this method on the target resource.',
                417 => 'The expectation given in an Expect request-header field could not be met by this server.',
                418 => 'The server refuses to brew coffee because it is, permanently, a teapot.',
                428 => 'The origin server requires the request to be conditional.',
                429 => 'The user has sent too many requests in a given amount of time ("rate limiting").',
                451 => 'The server is denying access to the resource as a consequence of a legal demand.',
                500 => 'The server encountered an unexpected condition which prevented it from fulfilling the request.',
                default => $exception->getMessage()
            };

        $extensions = $exception->additional ?? $exception->extensions ?? [];
        if ($detail != $exception->getMessage()) {
            $extensions['exception'] = $exception->getMessage();
        }

        return new ProblemDetails(
            'https://httpstatuses.com/' . $status,
            (new Response(status: $status))->getReasonPhrase(),
            $status,
            $detail,
            (string)$request->getUri(),
            $extensions
        );
    }
}
