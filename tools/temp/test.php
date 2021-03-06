<?php
/**
 * OpenSKOS
 *
 * LICENSE
 *
 * This source file is subject to the GPLv3 license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.txt
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   OpenSKOS
 * @package    OpenSKOS
 * @copyright  Copyright (c) 2015 Pictura Database Publishing. (http://www.pictura-dp.nl)
 * @author     Alexandar Mitsev
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt GPLv3
 */


include dirname(__FILE__) . '/../autoload.inc.php';

/* 
 * Updates the status expired to status obsolete
 */

require_once 'Zend/Console/Getopt.php';
$opts = array(
    'env|e=s' => 'The environment to use (defaults to "production")',
);

try {
    $OPTS = new Zend_Console_Getopt($opts);
} catch (Zend_Console_Getopt_Exception $e) {
    fwrite(STDERR, $e->getMessage()."\n");
    echo str_replace('[ options ]', '[ options ] action', $OPTS->getUsageMessage());
    exit(1);
}

include dirname(__FILE__) . '/../bootstrap.inc.php';

// Allow loading of application module classes.
$autoloader = new OpenSKOS_Autoloader();
$mainAutoloader = Zend_Loader_Autoloader::getInstance();
$mainAutoloader->pushAutoloader($autoloader, array('Editor_', 'Api_'));

class EchoLogger extends \Psr\Log\AbstractLogger
{
    public function log($level, $message, array $context = array())
    {
        echo $message . PHP_EOL;
    }
}

// Test...

$xml = '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
         xmlns:dc="http://purl.org/dc/terms/"
         xmlns:skos="http://www.w3.org/2004/02/skos/core#"
         xmlns:openskos="http://openskos.org/xmlns#">
<rdf:Description rdf:about="http://testing/6">
    <rdf:type rdf:resource="http://www.w3.org/2004/02/skos/core#Concept"/>
    <skos:prefLabel xml:lang="nl">Testing6</skos:prefLabel>
    <skos:notation>6</skos:notation>
    <openskos:tenant>beng</openskos:tenant>
    <openskos:toBeChecked>1</openskos:toBeChecked>
    
    <skos:inScheme rdf:resource="http://data.beeldengeluid.nl/gtaa/Persoonsnamen"/>
    <skos:topConceptOf rdf:resource="http://data.beeldengeluid.nl/gtaa/Persoonsnamen"/>
    <skos:narrower rdf:resource="http://testing/3"/>
    <skos:narrower rdf:resource="http://testing/1"/>
  </rdf:Description>
  </rdf:RDF>
';


//$xml = '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
//         xmlns:dc="http://purl.org/dc/terms/"
//         xmlns:skos="http://www.w3.org/2004/02/skos/core#"
//         xmlns:openskos="http://openskos.org/xmlns#">
//<rdf:Description rdf:about="http://testing/1">
//    <rdf:type rdf:resource="http://www.w3.org/2004/02/skos/core#Concept"/>
//    <skos:prefLabel xml:lang="nl">Testing1</skos:prefLabel>
//    <skos:notation>1</skos:notation>
//    <openskos:tenant>beng</openskos:tenant>
//    
//    <skos:inScheme rdf:resource="http://data.beeldengeluid.nl/gtaa/Persoonsnamen"/>
//  </rdf:Description>
//  </rdf:RDF>
//';


$client = new Zend_Http_Client('http://openskos/api/concept', array(
'maxredirects' => 0,
'timeout'      => 30));

$response = $client
    ->setEncType('text/xml')
    ->setRawData($xml)
    ->setParameterGet('tenant', 'beng')
    ->setParameterGet('collection', 'mycol')
    ->setParameterGet('key', 'alexandar')
    ->setParameterGet('autoGenerateIdentifiers', false)
    ->request('POST');

if ($response->isSuccessful()) {
    echo 'Concept created';
} else {
    echo 'Failed to create concept: ' . $response->getHeader('X-Error-Msg');
}


//$diContainer = Zend_Controller_Front::getInstance()->getDispatcher()->getContainer();
//$diContainer->get('OpenSkos2\Rdf\ResourceManager')->delete(new OpenSkos2\Rdf\Uri('http://testing/1'));
