<?php

declare(strict_types=1);

namespace Tapbuy\Forter\Test\Unit\Model;

use Magento\Framework\Validation\ValidationException;
use PHPUnit\Framework\TestCase;
use Tapbuy\Forter\Model\ForterResponseParser;

class ForterResponseParserTest extends TestCase
{
    private ForterResponseParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ForterResponseParser();
    }

    public function testParseThrowsOnNullResponse(): void
    {
        $this->expectException(ValidationException::class);
        $this->parser->parse(null);
    }

    public function testParseThrowsOnFalseResponse(): void
    {
        $this->expectException(ValidationException::class);
        $this->parser->parse(false);
    }

    public function testParseThrowsOnNonArrayResponse(): void
    {
        $this->expectException(ValidationException::class);
        $this->parser->parse('string-response');
    }

    public function testParseThrowsWhenMissingDataKey(): void
    {
        $this->expectException(ValidationException::class);
        $this->parser->parse(['success' => true]);
    }

    public function testParseThrowsWhenDataIsNotArray(): void
    {
        $this->expectException(ValidationException::class);
        $this->parser->parse(['data' => 'not-an-array']);
    }

    public function testParseThrowsWhenMissingForterDecisionKey(): void
    {
        $this->expectException(ValidationException::class);
        $this->parser->parse(['data' => ['status' => 'ok']]);
    }

    public function testParseReturnsDataArrayOnValidResponse(): void
    {
        $response = [
            'data' => [
                'forterDecision' => 'approve',
                'status' => 200,
                'recommendation' => 'REQUEST_SCA_EXEMPTION_TRA',
            ],
        ];

        $result = $this->parser->parse($response);

        $this->assertSame('approve', $result['forterDecision']);
        $this->assertSame(200, $result['status']);
        $this->assertSame('REQUEST_SCA_EXEMPTION_TRA', $result['recommendation']);
    }

    public function testParseReturnsDataWithDeclineDecision(): void
    {
        $response = [
            'data' => [
                'forterDecision' => 'DECLINE',
                'recommendation' => '',
            ],
        ];

        $result = $this->parser->parse($response);

        $this->assertSame('DECLINE', $result['forterDecision']);
    }
}
