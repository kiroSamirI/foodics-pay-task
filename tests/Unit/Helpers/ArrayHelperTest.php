<?php

namespace Tests\Unit\Helpers;

use App\Helpers\ArrayHelper;
use PHPUnit\Framework\TestCase;

class ArrayHelperTest extends TestCase
{
    public function testFlattenWithSimpleArray(): void
    {
        $input = [1, 2, 3];
        $expected = [1, 2, 3];
        
        $result = ArrayHelper::flatten($input);
        
        $this->assertEquals($expected, $result);
    }

    public function testFlattenWithNestedArrays(): void
    {
        $input = [
            'a',
            ['b', 'c'],
            ['d', ['e', 'f']]
        ];
        $expected = ['a', 'b', 'c', 'd', 'e', 'f'];
        
        $result = ArrayHelper::flatten($input);
        
        $this->assertEquals($expected, $result);
    }

    public function testFlattenWithEmptyArray(): void
    {
        $input = [];
        $expected = [];
        
        $result = ArrayHelper::flatten($input);
        
        $this->assertEquals($expected, $result);
    }

    public function testFlattenWithMixedTypes(): void
    {
        $input = [
            'string',
            123,
            ['nested' => 'value'],
            [true, false]
        ];
        $expected = ['string', 123, 'value', true, false];
        
        $result = ArrayHelper::flatten($input);
        
        $this->assertEquals($expected, $result);
    }

    public function testFlattenWithDeeplyNestedArrays(): void
    {
        $input = [
            'a',
            [
                'b',
                [
                    'c',
                    [
                        'd',
                        ['e']
                    ]
                ]
            ]
        ];
        $expected = ['a', 'b', 'c', 'd', 'e'];
        
        $result = ArrayHelper::flatten($input);
        
        $this->assertEquals($expected, $result);
    }

    public function testFlattenWithNullValues(): void
    {
        $input = [
            'a',
            null,
            ['b', null],
            [null, 'c']
        ];
        $expected = ['a', null, 'b', null, null, 'c'];
        
        $result = ArrayHelper::flatten($input);
        
        $this->assertEquals($expected, $result);
    }

    public function testFlattenWithAssociativeArrays(): void
    {
        $input = [
            'key1' => 'value1',
            'key2' => [
                'nested_key' => 'nested_value',
                'another_key' => [
                    'deep_key' => 'deep_value'
                ]
            ]
        ];
        $expected = ['value1', 'nested_value', 'deep_value'];
        
        $result = ArrayHelper::flatten($input);
        
        $this->assertEquals($expected, $result);
    }
} 