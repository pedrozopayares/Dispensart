<?php

namespace App\Http\Controllers\Assistant;

use App\Actions\Assistant\AskAssistant;
use App\Http\Requests\Assistant\AskAssistantRequest;
use App\Http\Resources\AssistantAnswerResource;
use App\Models\User;

final class AssistantController
{
    /**
     * Pregunta de inventario en español. `outcome` y `answer` los decide el servidor desde herramientas de solo
     * lectura que respetan el rol; el proveedor del modelo solo elige herramientas (503 si no está disponible).
     * `model` opcional elige el modelo entre los de GET /assistant/models; `data.model` dice cuál atendió.
     */
    public function __invoke(AskAssistantRequest $request, AskAssistant $ask): AssistantAnswerResource
    {
        /** @var User $user */
        $user = $request->user();

        return new AssistantAnswerResource($ask->handle($user, $request->question(), $request->model()));
    }
}
