<?php
use OpenSkos2\Namespaces\Rdf;

abstract class AbstractController extends OpenSKOS_Rest_Controller

{
    protected $fullNameResourceClass;
    
    
    public function init()
    {
        $this->getHelper('layout')->disableLayout();
        $this->getHelper('viewRenderer')->setNoRender(true);
        parent::init();
    }
    
      public function indexAction()
 {
        $format = $this->getParam('format');
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
        if ('html' === $this->getParam('format')) {
            throw new Exception('HTML format is not implemented yet', 404);
        }
        $this->_helper->viewRenderer->setNoRender(true);
        $request = $this->getPsrRequest();
        $api = $this->getDI()->make($this->fullNameResourceClass);
        $response = $api->findResourceById($request);
        $this->emitResponse($response);
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