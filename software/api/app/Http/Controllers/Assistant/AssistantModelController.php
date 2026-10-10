<?php

namespace App\Http\Controllers\Assistant;

use App\Http\Resources\AssistantModelResource;
use App\Services\Assistant\Llm\ModelCatalog;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AssistantModelController
{
    /**
     * Modelos elegibles para preguntar: `mock` siempre y primero; los de Ollama solo si están descargados, declaran
     * herramientas y Ollama responde en plazo. Ollama caído responde 200 solo con `mock`.
     */
    public function __invoke(ModelCatalog $catalog): AnonymousResourceCollection
    {
        return AssistantModelResource::collection($catalog->available());
    }
}
