<?php

require_once APPLICATION_PATH . '/../tests/application/Targeting/ATargeting.php';

/**
 * Classe de test pour le scénario N°1
 *
 * @group user
 */
class TargetingSmsTest extends ATargeting
{
    static protected $_media = 'SMS';
    protected $_contactsCount = 85;
}
