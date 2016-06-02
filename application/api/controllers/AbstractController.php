<?php


use EasyRdf\RdfNamespace;
use EasyRdf\Uri;
use OpenSkos2\Namespaces\vCard;

abstract class AbstractController extends OpenSKOS_Rest_Controller

{
    protected $fullNameResourceClass;
    protected $viewpath;
    public function init()
    {
        $this->getHelper('layout')->disableLayout();
        $this->getHelper('viewRenderer')->setNoRender(true);
        parent::init();
    }
    
    public function indexAction() {
        $format = $this->getRequestedFormat();
        if ('json' !== $format) {
            throw new Exception('Resource listing is implemented only in format=json', 404);
        }
        $request = $this->getPsrRequest();
        $api = $this->getDI()->make($this->fullNameResourceClass);
        $result = $api->fetchUriName($request);
        $this->_helper->contextSwitch()->setAutoJsonSerialization(false);
        $this->getResponse()->setBody(json_encode($result, JSON_UNESCAPED_SLASHES));
    }

    public function getAction() {
        $request = $this->getPsrRequest();
        $api = $this->getDI()->make($this->fullNameResourceClass);
        $id = $this->getRequest()->getParam('id');
        if (null === $id) {
            throw new Zend_Controller_Exception('No id provided', 400);
        }
        $context = $this->_helper->contextSwitch()->getCurrentContext();
        if ('html' === $context) {
           $this->view->resource = $api->findResourceById($id);
           $this->view->resProperties = $this ->preparePropertiesForHTML($this->view->resource);
           return $this->renderScript($this->viewpath . 'get.phtml');
        } else {
            $response = $api->findResourceByIdResponse($request, $id);
            $this->emitResponse($response);
        }
    }
    
    private function preparePropertiesForHTML($resource) {
        $props = $resource->getProperties();
        $retVal = [];
        $shortADR = RdfNamespace::shorten(vCard::ADR);
        $shortORG = RdfNamespace::shorten(vCard::ORG);
        foreach ($props as $propname => $vals) {
            $shortName = RdfNamespace::shorten($propname);
            if ($shortName !== $shortADR && $shortName !== $shortORG) {
                $shortHTMLName = $this->shortenForHTML($propname);
                foreach ($vals as $val) {
                    $retVal[$shortHTMLName] = $val;
                }
            } else { // recursive elements
                if ($vals !== null && isset($vals) && is_array($vals))
                    if (count($vals) > 0) {
                        foreach ($vals[0]->getProperties() as $key => $val2) {
                            $shortName2 = $this->shortenForHTML($key);
                            foreach ($val2 as $val3) {
                                if ($val3 instanceof Uri) {
                                    if ($shortName2 === 'license') {
                                        $retVal['license_url'] = $val3->getUri();
                                    } else {
                                       $retVal[$shortName2] = $val3->getUri(); 
                                    }
                                } else { // must be a literal
                                    if ($shortName2 === 'license') {
                                        $retVal['license_name'] = $val3->getValue();
                                    } else {
                                    $retVal[$shortName2] = $val3->getValue();
                                    }
                                }
                            }
                        }
                    }
            }
        }
        return $retVal;
    }

     private function shortenForHTML($key) {
        $parts = RdfNamespace::splitUri($key, false);
        return $parts[1];
    }

    public function postAction()
    {
        $request = $this->getPsrRequest();
        $api = $this->getDI()->make($this->fullNameResourceClass);
        $response = $api->create($request);
        $this->emitResponse($response);
    }
    
     public  function putAction()
    {
        $request = $this->getPsrRequest();
        $api = $this->getDI()->make($this->fullNameResourceClass);
        $response = $api->update($request);
        $this->emitResponse($response);
    }
    
     public  function deleteAction()
    {
        $request = $this->getPsrRequest();
        $api = $this->getDI()->make($this->fullNameResourceClass);
        $response = $api->deleteResourceObject($request);
        $this->emitResponse($response);
    }
    
   
}