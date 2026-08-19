<?php

namespace App\Services\Kommo;

/**
 * HTTP 403 do Kommo: possível bloqueio de IP por excesso de requisições.
 * Quando isso acontece, TODA a comunicação para — insistir piora o bloqueio.
 */
class KommoBlockedException extends KommoException {}
