<?php

require_once APPLICATION_PATH . '/../tests/application/Targeting/ATargeting.php';

/**
 * Classe de test pour le scénario N°1
 *
 * @group user
 */
class TargetingEmailTest extends ATargeting
{
    static protected $_media = 'EMAIL';
    protected $_contactsCount = 338;

}
