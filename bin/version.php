<?php
/**
 * The version lives in exactly one place — the plugin header. This propagates it to the constant,
 * readme.txt, package.json and uninstall.php, or with --check verifies they already agree.
 *
 * Usage: php bin/version.php [--check]
 */

$root   = dirname( __DIR__ );
$header = file_get_contents( $root . '/ai-parseable.php' );
if ( ! preg_match( '/^ \* Version:\s+(\S+)$/m', $header, $m ) ) {
	fwrite( STDERR, "Could not read the Version header.\n" );
	exit( 1 );
}
$version = $m[1];
$check   = in_array( '--check', $argv, true );
$targets = array(
	'ai-parseable.php' => array( "/define\( 'AI_PARSEABLE_VERSION', '[^']+' \);/", "define( 'AI_PARSEABLE_VERSION', '{$version}' );" ),
	'uninstall.php'    => array( "/define\( 'AI_PARSEABLE_VERSION', '[^']+' \);/", "define( 'AI_PARSEABLE_VERSION', '{$version}' );" ),
	'readme.txt'       => array( '/^Stable tag: .+$/m', "Stable tag: {$version}" ),
	'package.json'     => array( '/"version": "[^"]+"/', "\"version\": \"{$version}\"" ),
);
$drift = 0;
foreach ( $targets as $file => $rule ) {
	$path    = $root . '/' . $file;
	$content = file_get_contents( $path );
	$updated = preg_replace( $rule[0], $rule[1], $content, 1 );
	if ( $updated !== $content ) {
		$drift++;
		if ( $check ) {
			echo "Version drift in {$file}\n";
		} else {
			file_put_contents( $path, $updated );
			echo "Updated {$file} to {$version}\n";
		}
	}
}
if ( $check ) {
	echo $drift ? "FAILED: {$drift} file(s) disagree with the header ({$version}).\n" : "OK: all files at {$version}.\n";
	exit( $drift ? 1 : 0 );
}
echo "Header version: {$version}\n";
