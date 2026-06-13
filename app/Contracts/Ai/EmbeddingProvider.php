<?php

namespace App\Contracts\Ai;

/**
 * Target del patrón Adapter — Generación de Embeddings.
 *
 * Define el contrato que el dominio usa para generar vectores de embeddings.
 * La implementación concreta encapsula el servicio externo y la API HTTP
 * sin filtrarlas al dominio.
 */
interface EmbeddingProvider
{
    /**
     * Genera un vector de embeddings a partir de un texto.
     *
     * @return array<int, float> Vector de embeddings normalizado.
     *                           Devuelve [] si el proveedor no está disponible o falla.
     *
     * @throws \RuntimeException Si el proveedor lanza un error irrecuperable.
     */
    public function generate(string $text): array;

    /**
     * Indica si el proveedor de embeddings está habilitado y configurado.
     *
     * Retorna false si la configuración está ausente o el servicio externo
     * no está disponible. Los clientes deben verificar esto antes de llamar a generate().
     */
    public function isAvailable(): bool;
}
