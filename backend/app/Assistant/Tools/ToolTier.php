<?php

namespace App\Assistant\Tools;

/** O que uma ferramenta faz com os dados, e por isso quanto cuidado ela pede. */
enum ToolTier: string
{
    /** Só consulta. Qualquer papel pode usar. */
    case Read = 'read';
    /** Grava na hora e dá para desfazer (itens de lista, lembretes). Parte 4.2. */
    case Immediate = 'immediate';
    /** Vira um card para a pessoa conferir e confirmar (lançamentos). Parte 4.2. */
    case Confirm = 'confirm';
    /** Card com o impacto em destaque antes de apagar. Parte 4.2. */
    case Explicit = 'explicit';

    public function writes(): bool
    {
        return $this !== self::Read;
    }
}
