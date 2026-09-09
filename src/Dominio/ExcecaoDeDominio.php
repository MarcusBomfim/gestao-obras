<?php

declare(strict_types=1);

namespace GestaoObras\Dominio;

use DomainException;

/**
 * Regra de negócio violada. É diferente de erro de programação: a mensagem
 * daqui pode ser mostrada a quem está usando o sistema.
 */
final class ExcecaoDeDominio extends DomainException
{
}
