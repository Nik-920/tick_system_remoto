<?php

namespace Tests\Unit\Architecture;

use App\Contracts\Ai\EmbeddingProvider;
use App\Contracts\Notifications\PushNotificationProvider;
use App\Services\Ai\HuggingFaceEmbeddingAdapter;
use App\Services\Firebase\FirebasePushNotificationAdapter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * AdapterBindingTest — Verifica que el contenedor IoC de Laravel
 * resuelve los contratos Adapter hacia sus implementaciones concretas.
 */
class AdapterBindingTest extends TestCase
{
    #[Test]
    public function container_binds_embedding_provider_to_hugging_face_adapter(): void
    {
        $binding = $this->app->getBindings();

        $this->assertArrayHasKey(
            EmbeddingProvider::class,
            $binding,
            'EmbeddingProvider debe estar registrado en el contenedor IoC.'
        );
    }

    #[Test]
    public function resolved_embedding_provider_is_instance_of_contract(): void
    {
        $instance = $this->app->make(EmbeddingProvider::class);

        $this->assertInstanceOf(
            EmbeddingProvider::class,
            $instance,
            'El contenedor debe resolver EmbeddingProvider como instancia del contrato.'
        );
    }

    #[Test]
    public function resolved_embedding_provider_is_instance_of_concrete_adapter(): void
    {
        $instance = $this->app->make(EmbeddingProvider::class);

        $this->assertInstanceOf(
            HuggingFaceEmbeddingAdapter::class,
            $instance,
            'El contenedor debe resolver EmbeddingProvider como HuggingFaceEmbeddingAdapter.'
        );
    }

    #[Test]
    public function container_binds_push_notification_provider_to_firebase_adapter(): void
    {
        $binding = $this->app->getBindings();

        $this->assertArrayHasKey(
            PushNotificationProvider::class,
            $binding,
            'PushNotificationProvider debe estar registrado en el contenedor IoC.'
        );
    }

    #[Test]
    public function resolved_push_notification_provider_is_instance_of_contract(): void
    {
        $instance = $this->app->make(PushNotificationProvider::class);

        $this->assertInstanceOf(
            PushNotificationProvider::class,
            $instance,
            'El contenedor debe resolver PushNotificationProvider como instancia del contrato.'
        );
    }

    #[Test]
    public function resolved_push_notification_provider_is_instance_of_concrete_adapter(): void
    {
        $instance = $this->app->make(PushNotificationProvider::class);

        $this->assertInstanceOf(
            FirebasePushNotificationAdapter::class,
            $instance,
            'El contenedor debe resolver PushNotificationProvider como FirebasePushNotificationAdapter.'
        );
    }

    #[Test]
    public function embedding_provider_binding_uses_bind_not_singleton(): void
    {
        $bindings = $this->app->getBindings();
        $shared = $bindings[EmbeddingProvider::class]['shared'] ?? true;

        $this->assertFalse(
            $shared,
            'EmbeddingProvider debe usar bind() no singleton() — los adapters son sin estado.'
        );
    }

    #[Test]
    public function push_notification_provider_binding_uses_bind_not_singleton(): void
    {
        $bindings = $this->app->getBindings();
        $shared = $bindings[PushNotificationProvider::class]['shared'] ?? true;

        $this->assertFalse(
            $shared,
            'PushNotificationProvider debe usar bind() no singleton() — los adapters son sin estado.'
        );
    }
}
