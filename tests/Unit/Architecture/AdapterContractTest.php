<?php

namespace Tests\Unit\Architecture;

use App\Contracts\Ai\EmbeddingProvider;
use App\Contracts\Notifications\PushNotificationProvider;
use App\Services\Ai\HuggingFaceEmbeddingAdapter;
use App\Services\Firebase\FirebasePushNotificationAdapter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakeEmbeddingProvider;
use Tests\Fakes\FakePushNotificationProvider;

/**
 * AdapterContractTest — Tests arquitectónicos del patrón Adapter.
 *
 * Verifica que los adapters concretos y fakes implementan
 * sus contratos (interfaces objetivo) de forma explícita.
 *
 * Criterio protegido: #1, #2, #3, #7 (GoF Adapter formal)
 */
class AdapterContractTest extends TestCase
{
    // ──────────────────────────────────────────────────────────────
    // Adapter IA / Embeddings
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function hugging_face_embedding_adapter_implements_embedding_provider(): void
    {
        $implementations = class_implements(HuggingFaceEmbeddingAdapter::class);

        $this->assertIsArray($implementations);
        $this->assertArrayHasKey(
            EmbeddingProvider::class,
            $implementations,
            'HuggingFaceEmbeddingAdapter debe implementar EmbeddingProvider.'
        );
    }

    #[Test]
    public function fake_embedding_provider_implements_embedding_provider(): void
    {
        $implementations = class_implements(FakeEmbeddingProvider::class);

        $this->assertIsArray($implementations);
        $this->assertArrayHasKey(
            EmbeddingProvider::class,
            $implementations,
            'FakeEmbeddingProvider debe implementar EmbeddingProvider.'
        );
    }

    #[Test]
    public function embedding_provider_is_interface(): void
    {
        $this->assertTrue(
            interface_exists(EmbeddingProvider::class),
            'EmbeddingProvider debe ser una interfaz.'
        );
    }

    #[Test]
    public function hugging_face_adapter_has_generate_method(): void
    {
        $this->assertTrue(
            method_exists(HuggingFaceEmbeddingAdapter::class, 'generate'),
            'HuggingFaceEmbeddingAdapter debe tener el método generate().'
        );
    }

    #[Test]
    public function hugging_face_adapter_has_is_available_method(): void
    {
        $this->assertTrue(
            method_exists(HuggingFaceEmbeddingAdapter::class, 'isAvailable'),
            'HuggingFaceEmbeddingAdapter debe tener el método isAvailable().'
        );
    }

    // ──────────────────────────────────────────────────────────────
    // Adapter Firebase / Push Notifications
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function firebase_push_notification_adapter_implements_push_notification_provider(): void
    {
        $implementations = class_implements(FirebasePushNotificationAdapter::class);

        $this->assertIsArray($implementations);
        $this->assertArrayHasKey(
            PushNotificationProvider::class,
            $implementations,
            'FirebasePushNotificationAdapter debe implementar PushNotificationProvider.'
        );
    }

    #[Test]
    public function fake_push_notification_provider_implements_push_notification_provider(): void
    {
        $implementations = class_implements(FakePushNotificationProvider::class);

        $this->assertIsArray($implementations);
        $this->assertArrayHasKey(
            PushNotificationProvider::class,
            $implementations,
            'FakePushNotificationProvider debe implementar PushNotificationProvider.'
        );
    }

    #[Test]
    public function push_notification_provider_is_interface(): void
    {
        $this->assertTrue(
            interface_exists(PushNotificationProvider::class),
            'PushNotificationProvider debe ser una interfaz.'
        );
    }

    #[Test]
    public function firebase_adapter_has_send_to_user_method(): void
    {
        $this->assertTrue(
            method_exists(FirebasePushNotificationAdapter::class, 'sendToUser'),
            'FirebasePushNotificationAdapter debe tener el método sendToUser().'
        );
    }

    #[Test]
    public function firebase_adapter_has_send_to_role_method(): void
    {
        $this->assertTrue(
            method_exists(FirebasePushNotificationAdapter::class, 'sendToRole'),
            'FirebasePushNotificationAdapter debe tener el método sendToRole().'
        );
    }

    #[Test]
    public function firebase_adapter_has_send_to_roles_method(): void
    {
        $this->assertTrue(
            method_exists(FirebasePushNotificationAdapter::class, 'sendToRoles'),
            'FirebasePushNotificationAdapter debe tener el método sendToRoles().'
        );
    }
}
