<?php

require_once APPLICATION_PATH . '/../tests/application/ATestCase.php';

/**
 * Classe de test pour le scénario N°1
 *
 * @group user
 */
abstract class ATargeting extends ATestCase
{

    static protected $_media;
    protected $_contactsCount;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        \Ft\Service\AService::setCredential(static::getResources()->getConfig()->initScript->default->user->userKey);
    }

    /**
     *
     * @testdox Lecture des segments
     */
    public function testSegmentRead()
    {
        $segments = $this->getService('Targeting_Segment')->read();
        $this->assertNotEmpty($segments);
        $this->_addTestDatas('segments', $segments);
    }

    /**
     *
     * @depends testSegmentRead
     *
     * @testdox Création d'un order
     */
    public function testOrderCreate()
    {
        $orderName = "Ma commande de l'année";
        $order = $this->getService('Targeting_Order')->create(array('name' => $orderName));
        $this->assertEquals('CREATED', $order->status);
        $this->assertNotEmpty($order->id);

        $this->_addTestDatas('order', $order);
    }

    /**
     * @depends testOrderCreate
     *
     * @testdox Lecture de l'order créé et vérification du statut CREATED
     */
    public function testOrderReadCheckStatusCreated()
    {
        $this->_readId('Targeting_Order', 'order', array('status' => 'CREATED'));
    }

    /**
     * @depends testOrderReadCheckStatusCreated
     *
     * @testdox Estimation sur chaque segment et conservation du dernier
     */
    public function testOrderEstimate()
    {
        $order = $this->_getTestDatas('order');
        $segments = $this->_getTestDatas('segments');

        $datas['id'] = $order->id;
        $datas['zipcode'] = '29630';
        $datas['properties'] = $this->getService('Targeting_Order')->getProperties();

        foreach ($segments->list as $segment) {
            $datas['segment'] = $segment->name;
            $order = $this->getService('Targeting_Order')->estimate($datas);
            $this->assertEquals('ESTIMATED', $order->status);
            //check criteria
            foreach ((array) $order->criteria as $property => $value) {
                $expectedValue = isset($datas[$property]) ? $datas[$property] : null;
                if (is_array($value)) {
                    $this->assertContains($expectedValue, $value);
                } else {
                    $this->assertEquals($expectedValue, $value);
                }
            }
        }
        $this->_addTestDatas('order', $order);
    }

    /**
     * @depends testOrderEstimate
     *
     * @testdox Lecture de l'order estimé et vérification du statut ESTIMATED
     */
    public function testOrderReadCheckStatusEstimated()
    {
        $this->_readId('Targeting_Order', 'order', array('status' => 'ESTIMATED', 'listId' => null));
    }

    public function providerOrderRent()
    {
        $datas['media sms'] = array(
            array('media' => static::$_media),
            null,
        );

        $datas['rent is already done'] = array(
            array('media' => static::$_media),
            new \Exception('Parameter Only orders with ESTIMATED can be rented, RENTING status found', 417),
        );

        return $datas;
    }

    /**
     *
     * @depends testOrderReadCheckStatusEstimated
     * @dataProvider providerOrderRent
     *
     * @testdox Rent de l'order
     */
    public function testOrderRent($datas, $expectedResult, $params)
    {
        $order = $this->_getTestDatas('order');

        if ($expectedResult instanceof \Exception) {
            $this->setExpectedException(get_class($expectedResult), $expectedResult->getMessage(), $expectedResult->getCode());
        }

        $datas['id'] = $order->id;
        $datas['properties'] = $this->getService('Targeting_Order')->getProperties();
        $order = $this->getService('Targeting_Order')->rent($datas);
        $this->assertEquals('RENTING', $order->status);
        $this->assertNotEmpty($order->listId);

        $this->_addTestDatas('order', $order);
    }

    /**
     * @depends testOrderRent
     *
     * @testdox Lecture de l'order suite au rent et vérification du statut RENTING
     */
    public function testOrderReadCheckStatusRenting()
    {
        $order = $this->_getTestDatas('order');
        $this->assertNotEmpty($order->listId);
        $this->_readId('Targeting_Order', 'order', array('status' => 'RENTING', 'listId' => $order->listId));
    }

    /**
     * @depends testOrderReadCheckStatusRenting
     *
     * @testdox Vérification du nom de la liste de contact (fonction du nom de l'order) suite au rent.
     */
    public function testListReadCheckName()
    {
        $order = $this->_getTestDatas('order');
        $this->assertNotEmpty($order->listId);
        $contactList = $this->getService('Contact_List')->read(array('id' => $order->listId))->list[0];
        $this->assertNotNull($order->name);
        $this->assertNotEmpty($order->name);
        $this->assertEquals("Ma commande de l'année", $contactList->name);
    }

    /**
     * @depends testListReadCheckName
     *
     * @testdox Vérification de l'import de la liste
     */
    public function testListReadCheckListImported()
    {
        $order = $this->_getTestDatas('order');
        $this->_checkListImported($order->listId);
    }

    /**
     * @depends testListReadCheckListImported
     *
     * @testdox Lecture de l'order créé et vérification du statut RENTED
     */
    public function testOrderReadCheckStatusRented()
    {
        $order = $this->_getTestDatas('order');
        $this->assertNotEmpty($order->listId);
        $this->_readId('Targeting_Order', 'order', array('status' => 'RENTED', 'listId' => $order->listId));
    }

    /**
     * @depends testOrderReadCheckStatusRented
     *
     * @testdox Vérification de la création des contacts
     */
    public function testListReadCheckContactsCreated()
    {
        $order = $this->_getTestDatas('order');
        $this->_checkContactsCreated($order->listId, $this->_contactsCount);
    }

   /**
     * Méthode permettant de vérifier qu'une liste est bien importée
     *
     * @param int $listId identifiant de liste
     */
    protected function _checkContactsCreated($listId, $expectedCount = null)
    {
        $datas['id'] = $listId;
        $datas['properties'] = array('id', 'importLines', 'importInvalid');
        $lists = $this->getService('Contact_List')->read($datas);

        $result = $this->getService('Contact_List')->read($datas);
        $this->assertNotEmpty($result->list[0]);

        $list = $result->list[0];

        $this->assertNotEmpty($list->importLines);
        $this->assertNotNull($list->importInvalid);

        if ($expectedCount !== null) {
            $this->assertEquals($expectedCount, (int) $list->importLines - (int) $list->importInvalid);
        }
        else {
            $this->assertNotEquals(0, (int) $list->importLines);
            $this->assertEquals(0, (int) $list->importInvalid);
        }

        $this->_addTestDatas('list', $list);
    }
}
