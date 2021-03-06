<?php

/*
|--------------------------------------------------------------------------
| Bootstrap
|--------------------------------------------------------------------------
|
| Define the constants and the functions required by the script.
|
*/
define('COLOR_END', "\033[0m");
define('COLOR_GREEN', "\033[0;32m");
define('COLOR_RED', "\033[0;31m");
define('COLOR_YELLOW', "\033[1;33m");
define('COLOR_WHITE', "\033[1;37m");

/**
 * Abort the script.
 *
 * @return void
 */
function fireShutdown()
{
    shutdown('The script has been stopped.');
}

/**
 * Print a message on the screen.
 *
 * @param  string  $message
 * @param  string  $color
 * @return void
 */
function message(string $message, string $color = COLOR_WHITE) : void
{
    echo $color.$message.COLOR_END;
}

/**
 * Print a message followed by a line break on the screen.
 *
 * @param  string  $message
 * @param  string  $color
 * @return void
 */
function line(string $message, string $color = COLOR_WHITE) : void
{
    message($message.PHP_EOL, $color);
}

/**
 * Print a success message followed by a line break on the screen.
 *
 * @param  string  $message
 * @param  string  $color
 * @return void
 */
function success(string $message, string $color = COLOR_GREEN) : void
{
    line($message, $color);
}

/**
 * Halt the execution of the script with a message.
 *
 * @param  string  $message
 * @param  string  $color
 * @return void
 */
function shutdown(string $message, string $color = COLOR_RED) : void
{
    line($message, $color);
    exit;
}

/*
|--------------------------------------------------------------------------
| Gate Keeper
|--------------------------------------------------------------------------
|
| Make sure that the script is executed from the command-line.
|
*/
if (! isset($argv)) {
    shutdown('You must run the script from the command-line.');
}

/*
|--------------------------------------------------------------------------
| Register the handles
|--------------------------------------------------------------------------
|
| Allow the user to stop the abort of the script.
|
*/
message('Registering the handles... ');
declare(ticks=1);
pcntl_signal(SIGTERM, 'fireShutdown');
pcntl_signal(SIGINT, 'fireShutdown');
success('OK!');

/*
|--------------------------------------------------------------------------
| Script Options
|--------------------------------------------------------------------------
|
| Parse the options provided by the user and make sure the required options
| has been provided.
|
*/
foreach ($argv as $arg) {
    if (! preg_match('/--([^=]+)=([^\s]+)/', $arg, $matches)) {
        continue;
    }

    ${$matches[1]} = $matches[2];
}

if (! isset($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
    shutdown('You must provide the url of the GT Metrix report.');
}

if (! isset($domain) || filter_var($domain, FILTER_VALIDATE_URL) === false) {
    shutdown('You must provide the domain of your website.');
}
$domain = rtrim($domain, '/');

/*
|--------------------------------------------------------------------------
| Send Request
|--------------------------------------------------------------------------
|
| Retrieve the images to optimize from GT Metrix.
|
*/
message(sprintf('Sending the request to %s%s%s...', COLOR_YELLOW, $url, COLOR_END));
$dom = new DOMDocument;
@$dom->loadHTML(file_get_contents($url));
success('OK!');

message('Retrieving the nodes from the report... ');
$path = new DOMXPath($dom);
$nodes = $path->query("//tr[contains(@class, 'audit-uses-optimized-images')]");
success('OK!');

$totalNodes = count($nodes);
if ($totalNodes == 0) {
    shutdown('No image needs optimization.', COLOR_GREEN);
}
line(sprintf('Found %s%d%s node%s!', COLOR_YELLOW, $totalNodes, COLOR_END, $totalNodes > 1 ? 's' : ''));

/*
|--------------------------------------------------------------------------
| Download
|--------------------------------------------------------------------------
|
| Loop through the nodes to download the images.
|
*/
foreach ($nodes as $index => $node) {
    message(PHP_EOL.sprintf('Parsing node #%d... ', $index + 1));
    $links = $node->getElementsByTagName('a');

    if (count($links) !== 3) {
        line('FAILED!', COLOR_RED);
        continue;
    }
    success('OK!');

    list($emptyNode, $sourceNode, $newNode) = $links;
    $source = $sourceNode->getAttribute('href');
    $new = 'https://gtmetrix.com'.$newNode->getAttribute('href');

    message(sprintf('Checking source image %s%s%s... ', COLOR_YELLOW, $source, COLOR_END));
    if (strpos($source, $domain) !== 0) {
        line('SKIPPED!', COLOR_YELLOW);
        continue;
    }
    success('OK!');

    /**
     * Create the required subdirectories to save the images.
     */
    $path = __DIR__;
    $destination = str_replace($domain, getcwd(), $source);
    $directories = explode('/', str_replace(__DIR__.'/', '', $destination));

    array_pop($directories);

    foreach ($directories as $directory) {
        $path .= "/{$directory}";

        if (file_exists($path)) {
            continue;
        }

        message(sprintf('Creating directory %s%s%s...', COLOR_YELLOW, $path, COLOR_END));
        mkdir($path);
        success('OK!');
    }

    message(sprintf('Saving the image from %s%s%s to %s%s%s... ', COLOR_YELLOW, $new, COLOR_END, COLOR_YELLOW, $destination, COLOR_END));
    file_put_contents($destination, file_get_contents($new));
    success('OK!');
}
