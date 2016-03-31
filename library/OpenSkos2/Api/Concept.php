<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace OpenSkos2\Api;

use OpenSkos2\Namespaces\Dcmi;
use OpenSkos2\Namespaces\Skos;
use OpenSkos2\Namespaces\OpenSkos;
use OpenSkos2\Namespaces\Org;
use OpenSkos2\ConceptManager;
use OpenSkos2\Api\Exception\ApiException;
use OpenSkos2\Api\Exception\InvalidArgumentException;
use OpenSkos2\Api\Exception\UnauthorizedException;
use Psr\Http\Message\ServerRequestInterface as PsrServerRequestInterface;
use OpenSKOS_Db_Table_Row_User;
use OpenSkos2\Api\Response\ResultSet\JsonResponse;
use OpenSkos2\Api\Response\ResultSet\JsonpResponse;
use OpenSkos2\Api\Response\ResultSet\RdfResponse;
use OpenSkos2\Api\Response\Detail\JsonResponse as DetailJsonResponse;
use OpenSkos2\Api\Response\Detail\JsonpResponse as DetailJsonpResponse;
use OpenSkos2\Api\Response\Detail\RdfResponse as DetailRdfResponse;

use  OpenSkos2\Api\Transform\DataRdf;

class Concept extends AbstractTripleStoreResource {
    
     /**
     * Search autocomplete
     *
     * @var \OpenSkos2\Search\Autocomplete
     */
    private $searchAutocomplete;

    /**
     * Amount of concepts to return
     *
     * @var int
     */
    private $limit = 20;

    public function __construct(ConceptManager $manager,  \OpenSkos2\Search\Autocomplete $searchAutocomplete) {
        $this->manager = $manager;
        $this->searchAutocomplete = $searchAutocomplete;
    }
    
     /**
     * Map the following requests
     *
     * /api/find-concepts?q=Kim%20Holland
     * /api/find-concepts?&fl=prefLabel,scopeNote&format=json&q=inScheme:"http://uri"
     * /api/find-concepts?format=json&fl=uuid,uri,prefLabel,class,dc_title&id=http://data.beeldengeluid.nl/gtaa/27140
     * /api/concept/82c2614c-3859-ed11-4e55-e993c06fd9fe.rdf
     *
     * @param ServerRequestInterface $request
     * @param string $context
     * @return ResponseInterface
     */
    public function findConcepts(PsrServerRequestInterface $request, $context)
    {
        $params = $request->getQueryParams();
        
        // offset
        $start = 0;
        if (!empty($params['start'])) {
            $start = (int)$params['start'];
        }

        // limit
        $limit = $this->limit;
        if (isset($params['rows']) && $params['rows'] < 1001) {
            $limit = (int)$params['rows'];
        }
        
        $options = [
            'start' => $start,
            'rows' => $limit,
            'directQuery' => true,
        ];
        
        // tenant
        if (isset($params['tenant'])) {
            $tenant= $this->getTenantFromParams($params);
            $options['tenants'] = [$tenant->code];
        }
        
        // search query
        if (isset($params['q'])) {
            $options['searchText'] = $params['q'];
        }
        
        // sorting
       //Meertens was here
        if (isset($params['sorts'])) {
            $sortmap = $this->prepareSortsForSolr($params['sorts']);
            $options['sorts'] = $sortmap;
        }
        
         if (isset($params['skosCollection'])) {
            $options['skosCollection'] = explode(' ', trim($params['skosCollection']));
        }
        
        //sets (former tenant collections)
        // meertens was here
        if (isset($params['sets'])) {
            $options['sets'] = explode(' ', trim($params['sets']));
        }
        
        //meertesn was here
        if (isset($params['tenants'])) {
           $options['tenants'] = explode(' ', trim($params['tenants']));
        }
        //meertens was here
        if (isset($params['conceptScheme'])) {
            $options['conceptScheme'] = explode(' ', trim($params['conceptScheme']));
        }
        
        if (isset($params['status'])) {
            $options['status'] = explode(' ', trim($params['status']));
        }
        
        $concepts = $this->searchAutocomplete->search($options, $total);
        //var_dump($concepts);
        
        $result = new ResourceResultSet($concepts, $total, $start, $limit);
        //var_dump($result);
        
        if (isset($params['fl'])) {
            $propertiesList = $this->fieldsListToProperties($params['fl']);
        } else {
            $propertiesList = [];
        }
        
        switch ($context) {
            case 'json':
                $response = (new JsonResponse($result, $propertiesList))->getResponse();
                break;
            case 'jsonp':
                $response = (new JsonpResponse($result, $params['callback'], $propertiesList))->getResponse();
                break;
            case 'rdf':
                $response = (new RdfResponse($result, $propertiesList))->getResponse();
                break;
            default:
                throw new InvalidArgumentException('Invalid context: ' . $context);
        }

        return $response;
    }

    
    
     /**
     * Get PSR-7 response for concept
     *
     * @param $request \Psr\Http\Message\ServerRequestInterface
     * @param string $context
     * @throws NotFoundException
     * @throws InvalidArgumentException
     * @return ResponseInterface
     */
    public function getConceptResponse(PsrServerRequestInterface $request, $uuid, $context)
    {
        $concept = $this->getConcept($uuid);

        $params = $request->getQueryParams();
        
        if (isset($params['fl'])) {
            $propertiesList = $this->fieldsListToProperties($params['fl']);
        } else {
            $propertiesList = [];
        }
        switch ($context) {
            case 'json':
                $response = (new DetailJsonResponse($concept, $propertiesList))->getResponse();
                break;
            case 'jsonp':
                $response = (new DetailJsonpResponse($concept, $params['callback'], $propertiesList))->getResponse();
                break;
            case 'rdf':
                $response = (new DetailRdfResponse($concept, $propertiesList))->getResponse();
                break;
            default:
                throw new InvalidArgumentException('Invalid context: ' . $context);
        }

        return $response;
    }

    /**
     * Get openskos concept
     *
     * @param string|\OpenSkos2\Rdf\Uri $id
     * @throws NotFoundException
     * @throws Exception\DeletedException
     * @return \OpenSkos2\Concept
     */
    public function getConcept($id)
    {
        /* @var $concept \OpenSkos2\Concept */
        if ($id instanceof \OpenSkos2\Rdf\Uri) {
            $concept = $this->manager->fetchByUri($id);
        } else {
            $concept = $this->manager->fetchByUuid($id);
        }
        
        if (!$concept) {
            throw new NotFoundException('Concept not found by id: ' . $id, 404);
        }

        if ($concept->isDeleted()) {
            throw new Exception\DeletedException('Concept ' . $id . ' is deleted', 410);
        }

        return $concept;
    }
    
    /**
     * Gets a list (array or string) of fields and try to recognise the properties from it.
     * @param array $fieldsList
     * @return array
     */
    protected function fieldsListToProperties($fieldsList)
    {
        if (!is_array($fieldsList)) {
            $fieldsList = array_map('trim', explode(',', $fieldsList));
        }
        
        $propertiesList = [];
        //olha was here, it used to be getOldToProperties();
        $fieldsMap = FieldsMaps::getNamesToProperties();
        
        // Tries to search for the field in fields map.
        // If not found there tries to expand it from short property.
        // If not that - just pass it as it is.
        foreach ($fieldsList as $field) {
            if (!empty($field)) {
                if (isset($fieldsMap[$field])) {
                    $propertiesList[] = $fieldsMap[$field];
                } else {
                    $propertiesList[] = Namespaces::expandProperty($field);
                }
            }
        }
        
        // Check if we have a nice properties list at the end.
        foreach ($propertiesList as $propertyUri) {
            if ($propertyUri == 'uri') {
                continue;
            }
            if (filter_var($propertyUri, FILTER_VALIDATE_URL) == false) {
                throw new InvalidPredicateException(
                    'The field "' . $propertyUri . '" from fields list is not recognised.'
                );
            }
        }
        
        return $propertiesList;
    }
    
     public function delete(PsrServerRequestInterface $request)
    {
        try {
            $params = $request->getQueryParams();
            if (empty($params['id'])) {
                throw new InvalidArgumentException('Missing id parameter');
            }
            
            $id = $params['id'];
            /* @var $concept \OpenSkos2\Concept */
            $concept = $this->manager->fetchByUri($id);
            if (!$concept) {
                throw new NotFoundException('Concept not found by id :' . $id, 404);
            }
            
            $user = $this->getUserFromParams($params);
            $tenant = $this->getTenantFromParams($params);
            
            $this->resourceEditAllowed($user, $tenant, $concept);
            
            $this->manager->deleteSoft($concept);
        } catch (ApiException $ex) {
            return $this->getErrorResponse($ex->getCode(), $ex->getMessage());
        }
        
        $xml = (new DataRdf($concept))->transform();
        return $this->getSuccessResponse($xml, 202);
    }
    
   
    public function resourceEditAllowed(
        OpenSKOS_Db_Table_Row_User $user,
        $tenantcode,    
        $concept) {
        
        
        if ($user->tenant !== $tenantcode) {
            throw new UnauthorizedException('Tenant does not match user given', 403);
        }
        
        $resourceTenant = current($concept->getProperty(OpenSkos::TENANT));
        if ($tenantcode !== (string)$resourceTenant) {
            throw new UnauthorizedException('Resource has tenant ' . (string)$resourceTenant . ' which differs from the given ' . $tenantcode, 403);
        }
        if ($user->role !== 'admin') {
           throw new UnauthorizedException('You do not have permission to delete this concept', 403); 
        }
        return true;
    }
    
     /**
     * @param array $params
     * @return \OpenSKOS_Db_Table_Row_Tenant
     * @throws InvalidArgumentException
     */
    private function getTenantFromParams($params)
    {
        if (empty($params['tenant'])) {
            throw new InvalidArgumentException('No tenant specified', 412);
        }
            
        return $params['tenant'];
    }
    
    // specific content validation
    protected function validate($resourceObject, $tenantcode) {
        parent::validate($resourceObject, $tenantcode);
        
        // resources referred by uri's 
        $this->checkIfReferredResourcesExist($resourceObject);
    }

    // specific content validation
    protected function validateForUpdate($resourceObject, $tenantcode, $existingResourceObject) {
        parent::validateForUpdate($resourceObject, $tenantcode, $existingResourceObject);

        // resources referred by uri's
        $this->checkIfReferredResourcesExist($resourceObject);
    }

    // To DISCUSS?
    private function checkIfReferredResourcesExist($resourceObject) {
        $this->validateURI($resourceObject, OpenSkos::SET, Dcmi::DATASET);
        $this->validateURI($resourceObject, OpenSkos::INSKOSCOLLECTION, Skos::SKOSCOLLECTION);
        $this->validateURI($resourceObject, Skos::INSCHEME, Skos::CONCEPTSCHEME);
        
       
        $code = $resourceObject -> getTenant();
        $tenants = $this->manager->fetchSubjectWithPropertyGiven(OpenSkos::CODE, '"'.$code .'"', Org::FORMALORG);
        if (count($tenants) < 1) {
            throw new ApiException('The tenant referred by the code '. $code  .' does not exist.', 400);
        }
        
         
    }
    
   private function prepareSortsForSolr($sortstring) {
        //var_dump($sortstring);
        $sortlist = explode(" ", $sortstring);
        $l = count($sortlist);
        $sortmap = [];
        $i = 0;
        while ($i < $l - 1) { // the last element will be worked-on after the loop is finished
            $j = $i;
            $i++;
            $sortfield = $this->prepareSortFieldForSolr($sortlist[$j]); 
            $sortorder = 'asc';
            if ($sortlist[$i] === "asc" || $sortlist[$i] === 'desc') {
                $sortorder = $sortlist[$i];
                $i++;
            }
            $sortmap[$sortfield] = $sortorder;
        };
        if ($sortlist[$l - 1] !== 'asc' && $sortlist[$l - 1] !== 'desc') { // field name is the last and no order after it
            $sortfield = $this->prepareSortFieldForSolr($sortlist[$l-1]); // Fix "@nl" to "_nl"
            $sortmap[$sortfield] = 'asc';
        };
        return $sortmap;
    }
    
    private function prepareSortFieldForSolr($term) { // translate field name  to am internal sort-field name
        if (substr($term, 0, 5) === "sort_" || substr($term, strlen($term) -3, 1) === "_") { // is already an internal presentation ready for solr, starts with sort_* or *_langcode
            return $term;
        }
        if ($this->isDateField($term)) {
            return "sort_d_" . $term;
        } else {
            if (strpos($term, "@") !== false) { 
                return str_replace("@", "_", $term);
            } else {
                return "sort_s_" . $term;
            }
        }
    }

    private function isDateField($term){
        return ($term === "dateAccepted" || $term === "dateSubmitted" || $term === "modified");
    }
}
