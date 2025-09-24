<?php

namespace Core\Container\ServiceProvider;

use Closure;
use Laminas\Diactoros\{RequestFactory,
    ResponseFactory,
    ServerRequestFactory,
    StreamFactory,
    UploadedFileFactory,
    UriFactory};
use Laminas\HttpHandlerRunner\Emitter\{EmitterInterface, SapiEmitter};
use Laminas\HttpHandlerRunner\{RequestHandlerRunner, RequestHandlerRunnerInterface};
use Laminas\Stratigility\MiddlewarePipe;
use League\Container\ServiceProvider\AbstractServiceProvider;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\{RequestFactoryInterface,
    ResponseFactoryInterface,
    ServerRequestFactoryInterface,
    ServerRequestInterface,
    StreamFactoryInterface,
    UploadedFileFactoryInterface,
    UriFactoryInterface};
use Throwable;

class HttpServiceProvider extends AbstractServiceProvider
{

    public function __construct(
        private readonly Closure $pipeline_configurator
    ) {}

    public function provides(string $id): bool
    {
        return in_array($id, [
            ServerRequestInterface::class,
            ResponseFactoryInterface::class,
            RequestFactoryInterface::class,
            ServerRequestFactoryInterface::class,
            StreamFactoryInterface::class,
            UriFactoryInterface::class,
            UploadedFileFactoryInterface::class,
            RequestHandlerRunnerInterface::class,
            MiddlewarePipe::class,
            SapiEmitter::class
        ]);
    }

    public function register(): void
    {
        $this
            ->getContainer()
            ->add(
                ServerRequestInterface::class,
                static fn(): ServerRequestInterface => ServerRequestFactory::fromGlobals()
            );

        $this
            ->getContainer()
            ->add(ResponseFactoryInterface::class, ResponseFactory::class);

        $this
            ->getContainer()
            ->add(RequestFactoryInterface::class, RequestFactory::class);

        $this
            ->getContainer()
            ->add(ServerRequestFactoryInterface::class, ServerRequestFactory::class);

        $this
            ->getContainer()
            ->add(StreamFactoryInterface::class, StreamFactory::class);

        $this
            ->getContainer()
            ->add(UriFactoryInterface::class, UriFactory::class);

        $this
            ->getContainer()
            ->add(UploadedFileFactoryInterface::class, UploadedFileFactory::class);

        $this
            ->getContainer()
            ->add(
                RequestHandlerRunnerInterface::class,
                static fn(ContainerInterface $container): RequestHandlerRunnerInterface => new RequestHandlerRunner(
                    $container->get(MiddlewarePipe::class),
                    $container->get(EmitterInterface::class),
                    static fn(): ServerRequestInterface => $container->get(ServerRequestInterface::class),
                    static fn(Throwable $exception): ResponseFactoryInterface => $container->get(ResponseFactoryInterface::class)->createResponse(500)
                )
            )
            ->addArgument($this->getContainer());

        $this
            ->getContainer()
            ->add(MiddlewarePipe::class, function (ContainerInterface $container) {
                $pipeline = new MiddlewarePipe();

                ($this->pipeline_configurator)($pipeline, $container);

                return $pipeline;
            })
            ->addArgument($this->getContainer());

        $this
            ->getContainer()
            ->add(EmitterInterface::class, SapiEmitter::class);
    }
}
