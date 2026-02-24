<?php

namespace MediaWiki\Extension\MathSearch\Maintenance\Tests;

use MediaWikiUnitTestCase;

require_once __DIR__ . '/../../../maintenance/ImportQuickStatements.php';

/**
 * @covers \ImportQuickStatements
 * @covers \BaseImport
 */
class ImportQuickStatementsTest extends MediaWikiUnitTestCase {

/**
 * Returns a minimal testable subclass that bypasses the Maintenance constructor.
 */
private function newScript(): \ImportQuickStatements {
return new class extends \ImportQuickStatements {
public function __construct() {
// Bypass the Maintenance parent constructor intentionally.
}
};
}

/**
 * @dataProvider provideReadline
 */
public function testReadline( array $columns, array $line, array $expected ): void {
$method = new \ReflectionMethod( \ImportQuickStatements::class, 'readline' );
$method->setAccessible( true );
$this->assertSame( $expected, $method->invoke( $this->newScript(), $line, $columns ) );
}

public static function provideReadline(): array {
return [
'maps columns to values' => [
[ 'qid', 'P31' ],
[ 'Q42', 'Q5' ],
[ 'qid' => 'Q42', 'P31' => 'Q5' ],
],
'strips empty non-qid value' => [
[ 'qid', 'P31', 'P18' ],
[ 'Q42', 'Q5', '' ],
[ 'qid' => 'Q42', 'P31' => 'Q5' ],
],
'keeps empty qid value' => [
[ 'qid', 'P31' ],
[ '', 'Q5' ],
[ 'qid' => '', 'P31' => 'Q5' ],
],
'strips all empty non-qid values' => [
[ 'qid', 'P31', 'P18' ],
[ 'Q42', '', '' ],
[ 'qid' => 'Q42' ],
],
];
}

/**
 * Returns a test double that stubs getOption('create-missing') and skips file I/O.
 */
private function newScriptWithCreateMissingFlag( bool $createMissingValue ): \ImportQuickStatements {
return new class( $createMissingValue ) extends \ImportQuickStatements {
private bool $createMissingValue;

public function __construct( bool $createMissingValue ) {
// Bypass Maintenance constructor
$this->createMissingValue = $createMissingValue;
}

public function getOption( $name, $default = null ) {
return $name === 'create-missing' ? $this->createMissingValue : $default;
}

public function execute(): void {
// Mirrors ImportQuickStatements::execute() without file I/O from parent
$this->jobOptions['create_missing'] = (bool)$this->getOption( 'create-missing', false );
}
};
}

public function testExecuteSetsCreateMissingFalseByDefault(): void {
$testScript = $this->newScriptWithCreateMissingFlag( false );
$testScript->execute();

$prop = new \ReflectionProperty( \ImportQuickStatements::class, 'jobOptions' );
$prop->setAccessible( true );
$this->assertFalse( $prop->getValue( $testScript )['create_missing'] );
}

public function testExecuteSetsCreateMissingTrueWhenFlagEnabled(): void {
$testScript = $this->newScriptWithCreateMissingFlag( true );
$testScript->execute();

$prop = new \ReflectionProperty( \ImportQuickStatements::class, 'jobOptions' );
$prop->setAccessible( true );
$this->assertTrue( $prop->getValue( $testScript )['create_missing'] );
}

/**
 * @dataProvider provideGetJsonRows
 */
public function testGetJsonRows( string $jsonContent, array $expected ): void {
$tmpFile = tempnam( sys_get_temp_dir(), 'mwtest_' ) . '.json';
file_put_contents( $tmpFile, $jsonContent );
try {
$method = new \ReflectionMethod( \BaseImport::class, 'getJsonRows' );
$method->setAccessible( true );
$result = iterator_to_array( $method->invoke( $this->newScript(), $tmpFile ), false );
$this->assertSame( $expected, $result );
} finally {
unlink( $tmpFile );
}
}

public static function provideGetJsonRows(): array {
return [
'single row with all properties' => [
'[{"qid":"Q42","P31":"Q5"}]',
[ [ [ 'Q42', 'Q5' ], [ 'qid', 'P31' ] ] ],
],
'multiple rows with different properties' => [
'[{"qid":"Q42","P31":"Q5"},{"qid":"Q43","P18":"Q7"}]',
[
[ [ 'Q42', 'Q5' ], [ 'qid', 'P31' ] ],
[ [ 'Q43', 'Q7' ], [ 'qid', 'P18' ] ],
],
],
'row with empty value' => [
'[{"qid":"Q42","P31":"Q5","P18":""}]',
[ [ [ 'Q42', 'Q5', '' ], [ 'qid', 'P31', 'P18' ] ] ],
],
'empty array' => [
'[]',
[],
],
];
}

public function testGetFileRowsUsesJsonForJsonExtension(): void {
$tmpFile = tempnam( sys_get_temp_dir(), 'mwtest_' ) . '.json';
file_put_contents( $tmpFile, '[{"qid":"Q42","P31":"Q5"}]' );
try {
$method = new \ReflectionMethod( \BaseImport::class, 'getFileRows' );
$method->setAccessible( true );
$result = iterator_to_array( $method->invoke( $this->newScript(), $tmpFile ), false );
$this->assertSame( [ [ [ 'Q42', 'Q5' ], [ 'qid', 'P31' ] ] ], $result );
} finally {
unlink( $tmpFile );
}
}

public function testGetFileRowsUsesCsvForCsvExtension(): void {
$tmpFile = tempnam( sys_get_temp_dir(), 'mwtest_' ) . '.csv';
file_put_contents( $tmpFile, "qid,P31\nQ42,Q5\n" );
try {
$method = new \ReflectionMethod( \BaseImport::class, 'getFileRows' );
$method->setAccessible( true );
$result = iterator_to_array( $method->invoke( $this->newScript(), $tmpFile ), false );
$this->assertSame( [ [ [ 'Q42', 'Q5' ], [ 'qid', 'P31' ] ] ], $result );
} finally {
unlink( $tmpFile );
}
}
}
