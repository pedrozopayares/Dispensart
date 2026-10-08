<?php

namespace App\OpenApi;

use Dedoc\Scramble\Support\Generator\InfoObject;

/**
 * Info del documento con la licencia del repositorio (MIT, ADR-0005); Scramble no la expone.
 */
final class LicensedInfoObject extends InfoObject
{
    public static function from(InfoObject $info): self
    {
        $licensed = new self($info->title, $info->version);
        $licensed->setDescription($info->description);

        return $licensed;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [...parent::toArray(), 'license' => ['name' => 'MIT', 'identifier' => 'MIT']];
    }
}
