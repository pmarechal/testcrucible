<?php

require_once APPLICATION_PATH . '/../tests/application/ATestCase.php';
require_once APPLICATION_PATH . '/../tests/application/Targeting/ATargeting.php';

/**
 * Classe de test pour le scénario N°1
 *
 * Il s'agit de créer une campagne EMAIL et de s'arrurer que les en-tête et pied-de-page sont correctement injectés
 * dans le HTML lors de la construction des messages.
 * On reprend donc en grande partie le code des tests Messengeo/CreateCampaignTest.
 *
 * @group admin
 */
class TargetingHeaderFooterTest extends ATestCase// extends ATargeting
{
    static protected $_media = 'EMAIL';
    protected $_contactsCount = 338;

    // Original
    protected $_emailContent = <<<EOT
<html>
<head></head>
<body>
<a href='#PREVIEWLINK#'>preview</a>
<h1><b>FT</b> EMAIL message</h1>
Hello #firstName# #lastName#
<a href='#OPTOUTLINK#'>désabo</a>
</body>
</html>
EOT;

    protected $_emailContentAfterInjection = <<<EOT
<html>
<head></head>
<body>#header#
<a href='#PREVIEWLINK#'>preview</a>
<h1><b>FT</b> EMAIL message</h1>
Hello #firstName# #lastName#
<a href='#OPTOUTLINK#'>désabo</a>
#footer#</body>
</html>
EOT;

    public static function tearDownAfterClass()
    {
        // delete the campaign if an error occured int the tests
        try {
            if (isset(static::$_testDatas['campaign'])) {
                // in case the campaigns has not been deleted
                \Deo\Application::getInstance()->getService('Messengeo_Campaigns')
                    ->cancel(array('id' => static::$_testDatas['campaign']->id));
            }
        }catch (\Exception $e) {

        }

        // Should be called after Campaign cancellation since locked ContactLists cannot be flagged as deleted
        parent::tearDownAfterClass();
    }

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
        $orderName = "Ma location de contacts";
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
        $this->assertEquals("Ma location de contacts", $contactList->name);
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
        $datas['properties'] = array('id', 'name', 'importLines', 'importInvalid');
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

    /**
     * @testdox Vérification de la présence des champs #header# et #footer#
     * @depends testListReadCheckContactsCreated
     */
    public function testCreateCampaign()
    {
        $list = $this->_getTestDatas('list');

        $executionDate = date('Y-m-d H:i:s', strtotime('+5 days'));

        $datas = array(
            'name' => 'FT campaign ' . $executionDate,
            'listId' => $list->id,
            'reference' => '#12345',
            'type' => 'STANDARD',
            'steps' => array(
                array(
                    'mode' => 'combined',
                    'date' => $executionDate,
                    'mailings' => array(
                        array(
                            'media' => 'EMAIL',
                            'text' => 'FT EMAIL message',
                            'html' => $this->_emailContent,
                            'subject' => 'FT EMAIL subject',
                            'replyContact' => 'test+reply@digitaleo.com',
                            'sender' => 'test+sender@digitaleo.com',
                        ),
                    ),
                ),
            ),
        );

        $createResult = $this->getService('Messengeo_Campaigns')->create($datas);
        $this->assertEquals(1, $createResult->size);

        // check list status
        $lists = $this->getService('Contact_List')->read(array('id' => $list->id, 'properties' => array('id', 'importStatus')));
        $this->assertEquals('lock', $lists->list[0]->importStatus);

        // check campaign
        $campaign = $createResult->list[0];
        $this->_addTestDatas('campaign', $campaign);

        $this->assertEquals('FT campaign ' . $executionDate, $campaign->name);
        $this->assertEquals('STANDARD', $campaign->type);
        $this->assertEquals('#12345', $campaign->reference);
        $this->assertEquals($list->id, $campaign->listId);
        $this->assertEquals($list->name, $campaign->listName);
        $this->assertGreaterThan(0, $campaign->listCount);
        $this->assertEquals($executionDate, $campaign->dateStart);
        $this->assertEquals($executionDate, $campaign->dateEnd);
        $this->assertEquals('created', $campaign->status);

        // check steps
        $stepResult = $this->getService('Messengeo_Steps')->read(array('campaignId' => $campaign->id));
        $this->assertEquals(1, $stepResult->size);
        $step = $stepResult->list[0];

        $this->assertEquals($campaign->id, $step->campaignId);
        $this->assertEquals($campaign->name, $step->campaignName);
        $this->assertEquals('combined', $step->mode);
        $this->assertEquals($executionDate, $step->date);
        $this->assertEquals(0, count(array_diff(array('EMAIL'), $step->medias)));
        $this->assertGreaterThan(0, $step->campaignListCount);
        $this->assertEquals('created', $step->status);

        // Check mailings

        // Get 'admin' role for Messengeo Mailing reads because of 'htmlToSend' field
        \Ft\Service\AService::setCredential(static::getResources()->getConfig()->baseo->key);

        $mailingResult = $this->getService('Messengeo_Mailings')->read(array('stepId' => $step->id));

        // Back to normal user
        \Ft\Service\AService::setCredential(static::getResources()->getConfig()->initScript->default->user->userKey);

        $this->assertEquals(1, $mailingResult->size);
        $mailingEmail = $mailingResult->list[0];

        // Check EMAIL mailing
        $this->assertEquals($step->id, $mailingEmail->stepId);
        $this->assertEquals('email', $mailingEmail->media);
        $this->assertEquals($campaign->name . ' - step 1', $mailingEmail->name); // defined by Messengeo
        $this->assertEquals('test+reply@digitaleo.com', $mailingEmail->replyContact);
        $this->assertEquals('test+sender@digitaleo.com', $mailingEmail->sender);
        $this->assertEquals($executionDate, $mailingEmail->date);
        $this->assertEquals(0, $mailingEmail->nbMessages);
        $this->assertTrue($mailingEmail->guid != $mailingEmail->name);
        $this->assertEquals('created', $mailingEmail->status);

        // Check Header and Footer injection
        $this->assertEquals('#header#FT EMAIL message#footer#', $mailingEmail->text);
        $this->assertEquals($this->_emailContentAfterInjection, $mailingEmail->html);

        // Check HTML content
        $this->assertContains("<div style=\"width:100%;\" ><p style=\"font-family: Arial, Helvetica,sans-serif; font-size: 12px; text-align: center; max-width: 650px; margin: 0 auto;\">#text1#</p></div>", $mailingEmail->htmlToSend);
        $this->assertContains("<div style=\"width:100%;\" ><p style=\"font-family: Arial, Helvetica,sans-serif; font-size: 12px; text-align: center; max-width: 650px; margin: 0 auto;\">#text2#</p></div>", $mailingEmail->htmlToSend);
        $this->assertNotContains("#header#", $mailingEmail->htmlToSend);
        $this->assertNotContains("#footer#", $mailingEmail->htmlToSend);

        // Check TEXT content
        $this->assertContains("#text1#", $mailingEmail->textToSend);
        $this->assertContains("#text2#", $mailingEmail->textToSend);
        $this->assertNotContains("#header#", $mailingEmail->textToSend);
        $this->assertNotContains("#footer#", $mailingEmail->textToSend);

        // Wait for the build of the step
        $stepsBuilt = $this->_checkStepsBuilt(array($step->id));
        $this->assertTrue($stepsBuilt, 'campaign built');

        // TODO: check real final HTML and TEXT messages made by the builder
    }

    /**
     * @depends testCreateCampaign
     */
    public function testReadStepsByMedia()
    {
        $params = array(
            'medias' => array('email'),
            'campaignId' => $this->_getTestDatas('campaign')->id,
            'properties' => array('medias'),
        );
        $readResult = $this->getService('Messengeo_Steps')->read($params);
        $this->assertTrue($readResult->size > 0);
        foreach ($readResult->list as $step) {
            $this->assertContains('EMAIL', $step->medias);
        }
    }

    /**
     * @depends testCreateCampaign
     */
    public function testCancelCampaign()
    {
        $campaign = $this->_getTestDatas('campaign');
        $this->getService('Messengeo_Campaigns')->cancel(array('id' => $campaign->id));

        // check list status
        $lists = $this->getService('Contact_List')->read(array('id' => $campaign->listId, 'properties' => array('id', 'importStatus')));
        $this->assertEquals('ok', $lists->list[0]->importStatus);

        // Lecture de la campagne + vérification
        $readResult = $this->getService('Messengeo_Campaigns')->read(array('id' => $campaign->id));
        $this->assertEquals(1, $readResult->size);
        $cancelledCampaign = $readResult->list[0];
//var_dump($cancelledCampaign);
        $this->assertTrue($cancelledCampaign->dateStart != $cancelledCampaign->dateEnd);
        $this->assertStringStartsWith(date('Y-m-d'), $cancelledCampaign->dateEnd);
        $this->assertEquals('cancelled', $cancelledCampaign->status);
    }
}
