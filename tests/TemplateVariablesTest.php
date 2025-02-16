<?php

namespace Tests;

use ByJG\JinjaPhp\Exception\TemplateParseException;
use PHPUnit\Framework\TestCase;

class TemplateVariablesTest extends TestCase
{
    public function testRender(): void
    {
        $template = new \ByJG\JinjaPhp\Template("Test var1: {{ var1 }}.");
        $this->assertEquals("Test var1: value1.", $template->render(['var1' => 'value1']));
    }

    public function testRenderWithUndefined(): void
    {
        $template = new \ByJG\JinjaPhp\Template("Test var1: {{ var1 }}.");
        $template->withUndefined(new \ByJG\JinjaPhp\Undefined\DebugUndefined());
        $this->assertEquals("Test var1: {{ NOT_FOUND: var1 }}.", $template->render(['var2' => 'value1']));
    }

    public function testRenderWithUndefinedStrict(): void
    {
        $template = new \ByJG\JinjaPhp\Template("Test var1: {{ var1 }}.");
        $template->withUndefined(new \ByJG\JinjaPhp\Undefined\StrictUndefined());
        $this->expectException(\Exception::class);
        $template->render(['var2' => 'value1']);
    }

    public function testRenderWithUndefinedDefault(): void
    {
        $template = new \ByJG\JinjaPhp\Template("Test var1: {{ var1 }}.");
        $template->withUndefined(new \ByJG\JinjaPhp\Undefined\DefaultUndefined());
        $this->assertEquals("Test var1: .", $template->render(['var2' => 'value1']));
    }

    public function testInvalidVar(): void
    {
        $this->expectException(TemplateParseException::class);
        $this->expectExceptionMessage("Variable xyz not defined");
        $template = new \ByJG\JinjaPhp\Template("{% for xyz in array %}{{ xyz }}{% endfor %}{{ xyz }}");
        $template->render(['array' => ['val1', 'val2']]);
    }

    public function testRenderArray(): void
    {
        $template = new \ByJG\JinjaPhp\Template("Test var1: {{ var1.0 }}.");
        $this->assertEquals("Test var1: value1.", $template->render(['var1' => ['value1']]));
    }

    // TODO: test with {{ var1[0] }}

    public function testRenderAssociativeArray(): void
    {
        $template = new \ByJG\JinjaPhp\Template("Test var1: {{ var1.key1 }}.");
        $this->assertEquals("Test var1: value1.", $template->render(['var1' => ['key1' => 'value1']]));
    }

    // TODO: test with {{ var1['key1'] }}

}