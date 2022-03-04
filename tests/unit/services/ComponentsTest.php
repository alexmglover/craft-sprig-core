<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\sprigcoretests\unit\services;

use Codeception\Test\Unit;
use Craft;
use craft\elements\Entry;
use putyourlightson\sprig\errors\InvalidVariableException;
use putyourlightson\sprig\Sprig;
use UnitTester;
use yii\base\Model;
use yii\web\Request;
use yii\web\BadRequestHttpException;

/**
 * @author    PutYourLightsOn
 * @package   Sprig
 * @since     1.0.0
 */

class ComponentsTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    protected function _before()
    {
        parent::_before();

        // Bootstrap the module
        Sprig::bootstrap();

        Craft::$app->getView()->setTemplatesPath(Craft::getAlias('@templates'));
    }

    public function testCreate()
    {
        $markup = Sprig::$core->componentsService->create(
            '_component',
            ['number' => 15],
            ['id' => 'abc', 's-trigger' => 'load', 's-vars' => 'limit:1', 's-push-url' => 'new-url']
        );
        $html = (string)$markup;

        $this->assertStringContainsString('id="abc"', $html);
        $this->assertStringContainsString('data-hx-include="#abc *"', $html);
        $this->assertStringContainsString('data-hx-trigger="load"', $html);
        $this->assertStringContainsString('sprig:template', $html);
        $this->assertStringContainsString('limit:1', $html);
        $this->assertStringContainsString('data-hx-push-url="new-url"', $html);
        $this->assertStringContainsString('xyz 15', $html);
    }

    public function testCreateEmptyComponent()
    {
        $markup = Sprig::$core->componentsService->create('_empty');
        $html = (string)$markup;

        $this->assertStringContainsString('data-hx-get', $html);
    }

    public function testCreateNoComponent()
    {
        $this->expectException(BadRequestHttpException::class);

        Sprig::$core->componentsService->create('_no-component');
    }

    public function testCreateObjectFromComponent()
    {
        // Require the class since it is not autoloaded.
        require CRAFT_TESTS_PATH.'/_craft/sprig/components/TestComponent.php';

        $object = Sprig::$core->componentsService->createObject(
            'TestComponent',
            ['number' => 15]
        );

        $html = $object->render();

        $this->assertStringContainsString('xyz 15', $html);
    }

    public function testCreateObjectFromNoComponent()
    {
        $object = Sprig::$core->componentsService->createObject('NoComponent');

        $this->assertNull($object);
    }

    public function testCreateInvalidVariableEntry()
    {
        $this->_testCreateInvalidVariable(['number' => '', 'entry' => new Entry()]);
    }

    public function testCreateInvalidVariableModel()
    {
        $this->_testCreateInvalidVariable(['number' => '', 'model' => new Model()]);
    }

    public function testCreateInvalidVariableObject()
    {
        $this->_testCreateInvalidVariable(['number' => '', 'model' => (object)[]]);
    }

    public function testCreateInvalidVariableArray()
    {
        $this->_testCreateInvalidVariable(['number' => '', 'array' => [new Entry()]]);
    }

    public function testGetParsedTagAttributes()
    {
        $html = '<div sprig s-method="post" s-action="a/b/c" s-vals=\'{"limit":1}\'></div>';
        $html = Sprig::$core->componentsService->parse($html);

        $this->assertStringContainsString('data-hx-post=', $html);
        $this->assertStringContainsString('&amp;sprig:action=', $html);
        $this->assertStringContainsString('data-hx-headers="{&quot;'.Request::CSRF_HEADER.'&quot;', $html);
        $this->assertStringContainsString('data-hx-vals="{&quot;limit&quot;:1}', $html);
    }

    public function testGetParsedTagAttributesWithData()
    {
        $html = '<div data-sprig></div>';
        $html = Sprig::$core->componentsService->parse($html);

        $this->assertStringContainsString('data-hx-get=', $html);
    }

    public function testGetParsedTagAttributesWithSpaces()
    {
        $html = '<div s-target = "#id" ></div>';
        $html = Sprig::$core->componentsService->parse($html);
        $this->assertStringContainsString('data-hx-target="#id"', $html);

        $html = '<div s-target = \'#id\' ></div>';
        $html = Sprig::$core->componentsService->parse($html);
        $this->assertStringContainsString('data-hx-target="#id"', $html);

        $html = '<div s-target = #id ></div>';
        $html = Sprig::$core->componentsService->parse($html);
        $this->assertStringContainsString('data-hx-target="#id"', $html);

        $html = '<div s-target = #id'.PHP_EOL.'></div>';
        $html = Sprig::$core->componentsService->parse($html);
        $this->assertStringContainsString('data-hx-target="#id"', $html);
    }

    public function testGetParsedTagAttributesWithTabs()
    {
        $html = '<div	s-target="#id"></div>';
        $html = Sprig::$core->componentsService->parse($html);
        $this->assertEquals('<div s-target="#id" data-hx-target="#id"></div>', $html);
    }

    public function testGetParsedTagAttributesVals()
    {
        $html = '<div s-val:x-y-z="a" s-vals=\'{"limit":1}\'></div>';
        $html = Sprig::$core->componentsService->parse($html);
        $this->assertStringContainsString('data-hx-vals="{&quot;xYZ&quot;:&quot;a&quot;,&quot;limit&quot;:1}"', $html);
    }

    public function testGetParsedTagAttributesValsWithEmpty()
    {
        $html = '<div s-val:x-y-z="" s-vals=\'{"limit":1}\'></div>';
        $html = Sprig::$core->componentsService->parse($html);
        $this->assertStringContainsString('data-hx-vals="{&quot;xYZ&quot;:&quot;&quot;,&quot;limit&quot;:1}"', $html);
    }

    public function testGetParsedTagAttributesValsWithEncoded()
    {
        $html = '<div s-val:x-y-z="a" s-vals=\'{&quot;limit&quot;:1}\'></div>';
        $html = Sprig::$core->componentsService->parse($html);
        $this->assertStringContainsString('data-hx-vals="{&quot;xYZ&quot;:&quot;a&quot;,&quot;limit&quot;:1}"', $html);
    }

    public function testGetParsedTagAttributesValsWithBrackets()
    {
        $html = '<div s-val:fields[x-y-z]="a"></div>';
        $html = Sprig::$core->componentsService->parse($html);
        $this->assertStringContainsString('data-hx-vals="{&quot;fields[xYZ]&quot;:&quot;a&quot;}"', $html);
    }

    public function testGetParsedTagAttributesValsEncodedAndSanitized()
    {
        $html = '<div s-val:x="alert(\'xss\')" s-val:z=\'alert("xss")\' s-vals=\'{"limit":1}\'></div>';
        $html = Sprig::$core->componentsService->parse($html);
        $this->assertStringContainsString('data-hx-vals="{&quot;x&quot;:&quot;alert(\u0027xss\u0027)&quot;,&quot;z&quot;:&quot;alert(\u0022xss\u0022)&quot;,&quot;limit&quot;:1}"', $html);
    }

    public function testGetParsedTagAttributesEmpty()
    {
        $html = '';
        $result = Sprig::$core->componentsService->parse($html);
        $this->assertEquals($html, $result);
    }

    public function testGetParsedTagAttributesHtml()
    {
        $html = '<div><p><span><template><h1>Hello</h1></template></span></p></div>';
        $result = Sprig::$core->componentsService->parse($html);
        $this->assertEquals($html, $result);
    }

    public function testGetParsedTagAttributesDuplicateIds()
    {
        $html = '<div id="my-id"><p id="my-id"><span id="my-id"></span></p></div>';
        $result = Sprig::$core->componentsService->parse($html);
        $this->assertEquals($html, $result);
    }

    public function testGetParsedTagAttributesComment()
    {
        $html = '<!-- Comment mentioning sprig -->';
        $result = Sprig::$core->componentsService->parse($html);
        $this->assertEquals($html, $result);
    }

    public function testGetParsedTagAttributesScript()
    {
        $html = '<script>if(i < 1) let sprig=1</script>';
        $result = Sprig::$core->componentsService->parse($html);
        $this->assertEquals($html, $result);
    }

    public function testGetParsedTagAttributesUtfEncoding()
    {
        $placeholder = 'ÆØÅäöü';
        $html = '<div sprig placeholder="'.$placeholder.'"></div>';
        $result = Sprig::$core->componentsService->parse($html);
        $this->assertStringContainsString($placeholder, $result);
    }

    private function _testCreateInvalidVariable(array $variables)
    {
        $this->tester->mockCraftMethods('view', ['doesTemplateExist' => true]);
        Craft::$app->getView()->setTemplatesPath(Craft::getAlias('@templates'));

        $this->expectException(InvalidVariableException::class);

        Sprig::$core->componentsService->create('_component', $variables);
    }
}
