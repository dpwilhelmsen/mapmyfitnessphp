<?php
/**
 * MapMyFitnessPHP v.0.1. Basic MapMyFitness API wrapper for PHP using OAuth
 *
 * Note: Library is in beta and provided as-is. We hope to add features as API grows, however
 *       feel free to fork, extend and send pull requests to us.
 *
 * This library is originally based off the FitbitPHP wrapper by heyitspavel, reworked to utilize
 * the MapMyFitness API.
 * - https://github.com/heyitspavel/fitbitphp
 *
 *
 * Date: 2013/14/04
 * Requires OAuth 1.0.0, SimpleXML
 * @version 0.1 ($Id$)
 */
namespace MapMyFitness;

class Api
{
    protected $service;

    protected $oauthToken;
    protected $oauthSecret;

    protected $userId = '-';
    protected $responseFormat;
    
    /**
     * Fetch response from API
     * 
     * @param string $route
     * @param string $method
     * @param array $parameters
     * @return 
     */
    private function fetchResponse($route, $method, $parameters)
    {
    	$response = $this->service->request($route, $method, $parameters);
    	return $this->parseResponse($response);
    }

    /**
     * @param string $consumer_key Application consumer key for MapMyFitness API
     * @param string $consumer_secret Application secret
     * @param int $debug Debug mode (0/1) enables OAuth internal debug
     * @param string $user_agent User-agent to use in API calls
     * @param string $response_format Response format (json or xml) to use in API calls
     */
    public function __construct($consumer_key, $consumer_secret, $callbackUrl = null, $responseFormat = 'json', \OAuth\Common\Storage\TokenStorageInterface $storageAdapter = null)
    {
        if (!in_array($responseFormat, array('json', 'xml', 'php', 'txt')))
        {
            throw new \Exception("Reponse format must be one of 'json', 'xml', 'php', 'txt'");
        }

        // If callback url wasn't set, use the current url
        if ($callbackUrl == null) {
            $uriFactory = new \OAuth\Common\Http\Uri\UriFactory();
            $currentUri = $uriFactory->createFromSuperGlobalArray($_SERVER);
            $currentUri->setQuery('');
            $callbackUrl = $currentUri->getAbsoluteUri();
        }

        $this->responseFormat = $responseFormat;

        $factory = new \OAuth\ServiceFactory();

        $credentials = new \OAuth\Common\Consumer\Credentials(
            $consumer_key,
            $consumer_secret,
            $callbackUrl
        );

        if ($storageAdapter == null)
        {
            $storageAdapter = new \OAuth\Common\Storage\Session();
        }

        $this->service = $factory->createService('MapMyFitness', $credentials, $storageAdapter);
    }

    public function isAuthorized() {
        return $this->service->getStorage()->hasAccessToken();
    }

    /**
    * Authorize the user
    *
    */
    public function initSession() {

        if (empty($_SESSION['mapMyFitness_Session']))
            $_SESSION['mapMyFitness_Session'] = 0;


        if (!isset($_GET['oauth_token']) && $_SESSION['mapMyFitness_Session'] == 1)
            $_SESSION['mapMyFitness_Session'] = 0;


        if ($_SESSION['mapMyFitness_Session'] == 0) {

            $token = $this->service->requestRequestToken();
            $url = $this->service->getAuthorizationUri(array('oauth_token' => $token->getRequestToken()));

            $_SESSION['mapMyFitness_Session'] = 1;
            header('Location: ' . $url);
            exit;

        } else if ($_SESSION['mapMyFitness_Session'] == 1) {

            $token = $this->service->getStorage()->retrieveAccessToken();
            // This was a callback request from MapMyFitness, get the token
            $this->service->requestAccessToken(
                $_GET['oauth_token'],
                $_GET['oauth_verifier'],
                $token->getRequestTokenSecret() );

            $_SESSION['mapMyFitness_Session'] = 2;

            return 1;

        }
    }

    /**
     * Reset session
     *
     * @return void
     */
    public function resetSession()
    {
        $this->service->getStorage()->clearToken();
        unset($_SESSION["mapMyFitness_Session"]);
    }

    /**
     * Set mapMyFitness userId for future API calls
     *
     * @param  $userId 'XXXXX'
     * @return void
     */
    public function setUser($userId)
    {
        $this->userId = $userId;
    }

    protected function verifyToken()
    {
        if(!$this->isAuthorized()) {
            throw new \Exception("You must be authorized to make requests");
        }
    }

    /**
     * API wrappers
     *
     */
    
    /**
     * Activity Feeds
     */
    
    /**
     * Get Activity Feed for User
     * 
     * @throws Exception
     * @param int $userId
     * @param int $userKey
     * @param DateTime $dateSince
     * @param string $scope
     * @param int $startRecord
     * @param int $limit
     * @param string $sortBy
     * @return mixed SimpleXMLElement, the value encoded in json as an object or PHP Array
     */
    public function getActivityFeed($userId = null, $userKey = null, $dateSince = null, $scope = null, $startRecord = null, $limit = null, $sortBy = null)
    {
    	$parameters = array();
    	if (isset($userId))
    		$parameters['user_id'] = $userId;
    	if (isset($userKey))
    		$parameters['user_key'] = $userKey;
    	if (isset($dateSince))
    		$parameters['date_since '] = $dateSince->format('Y-m-d');
    	if (isset($scope))
    		$parameters['scope'] = $scope;
    	if (isset($startRecord))
    		$parameters['start_record'] = $startRecord;
    	if (isset($limit))
    		$parameters['limit'] = $limit;
    	if (isset($sortBy))
    		$parameters['sort_by'] = $sortBy;
    	$parameters['o'] = $this->responseFormat;
    	
    	return $this->fetchResponse('activity_feed/get_activity_feed', 'GET', $parameters);
    }
    
    /**
     * Events
     */
    
    /**
     * Create Event
     * 
     * @throws Exception
     * @param string $eventTitle Title of the Event
     * @param DateTime $eventStartDate Event Start Date
     * @param string $eventTypeIdList Comma-Delimited List of Event Type IDs Must be same Parent Type (see events/get_event_types)  
     * @param int $eventCreatorType Event Creator Type 
     * @param string $eventCity Primary Event City
     * @param string $eventCountry Country Code (2 char)
     * @param string $eventBriefDescription Short / Brief Description
     * @param array $additionalOptions An array of additional, optional arguments, with key/values following the format outlined at {@link http://api.mapmyfitness.com/3.1/events/create_event}
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function createEvent($eventTitle, $eventStartDate, $eventTypeIdList, $eventCreatorType, $eventCity, $eventCountry, $eventBriefDescription, $additionalOptions = null)
    {
    	$parameters = array(
    		'event_title' => $eventTitle,
    		'event_start_date' => $eventStartDate->format('Y-m-d'),
    		'event_type_id_list' => $eventTypeIdList,
    		'event_creator_type' => $eventCreatorType,
    		'event_city' => $eventCity,
    		'event_country' => $eventCountry,
    		'event_brief_description' => $eventBriefDescription,
    		'o' => $this->responseFormat,
    	);
    	$parameters = array_merge($parameters, $additionalOptions);
    	return $this->fetchResponse('events/create_event', 'GET', $parameters);
    }
    
    /**
     * Delete Event
     * 
     * @throws Exception
     * @param int $eventKey Event Key
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function deleteEvent($eventKey)
    {
    	$parameters = array(
    		'event_key' => $eventKey,
			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('events/delete_event', 'GET', $parameters);
    }
    
    /**
     * Get Event
     * 
     * @throws Exception
     * @param int $eventKey Event Key
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getEvent($eventKey)
    {
    	$parameters = array(
    			'event_key' => $eventKey,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('events/get_event', 'GET', $parameters);
    }
    
    /**
     * Get Event Routes
     *
     * @throws Exception
     * @param int $eventKey Event Key
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getEventRoutes($eventKey)
    {
    	$parameters = array(
    			'event_key' => $eventKey,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('events/get_event_routes', 'GET', $parameters);
    }
    
    /**
     * Get Event Types
     * 
     * @throws Exception
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getEventTypes()
    {
    	$parameters = array(
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('events/get_event_types', 'GET', $parameters);
    }
    
    /**
     * Publish Event
     * 
     * @throws Exception
     * @param int_type $eventKey Event Key
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function publishEvent($eventKey)
    {
    	$parameters = array(
    			'event_key' => $eventKey,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('events/publish_event', 'GET', $parameters);
    }
    
    /**
     * Search Events
     * 
     * @throws Exception
     * @param array $additionalOptions  An array of additional, optional arguments, with key/values following the format outlined at {@link http://api.mapmyfitness.com/3.1/events/search_eventst}
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function searchEvents($additionalOptions)
    {
    	$parameters = array(
    			'o' => $this->responseFormat,
    	);
    	$parameters = array_merge($parameters, $additionalOptions);
    	return $this->fetchResponse('events/publish_event', 'GET', $parameters);
    }
    
    /**
     * Unpublish Event
     *
     * @throws Exception
     * @param int $eventKey Event Key
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function unpublishEvent($eventKey)
    {
    	$parameters = array(
    			'event_key' => $eventKey,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('events/unpublish_event', 'GET', $parameters);
    }
    
    /**
     * Gear
     */
    
    /**
     * This method creates an item of Gear for a User
     * 
     * @throws Exception
     * @param string $gearName Gear Name
     * @param unknown_type $gearTypeId
     * @param unknown_type $purchaseDate
     * @param unknown_type $additionalOptions
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function createGear($gearName, $gearTypeId, $purchaseDate, $additionalOptions)
    {
    	$parameters = array(
    			'gear_name' => $gearName,
    			'gear_type_id' => $gearTypeId,
    			'purchase_date' => $purchaseDate->format('Y-m-d'),
    			'o' => $this->responseFormat,
    	);
    	$parameters = array_merge($parameters, $additionalOptions);
    	return $this->fetchResponse('gear/create_gear', 'GET', $parameters);
    }
    
    /**
     * This method creates a new Brand of Gear.
     * 
     * @throws Exception
     * @param string $gearBrandName Gear Brand Name
     * @param int $gearTypeId Gear Type ID
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function createGearBrand($gearBrandName, $gearTypeId)
    {
    	$parameters = array(
    			'gear_brand_name' => $gearBrandName,
    			'gear_type_id' => $gearTypeId,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('gear/create_gear_brand', 'GET', $parameters);
    }
    
    /**
     * This method creates a new Type of Gear for a user.
     * 
     * @throws Exception
     * @param string $gearTypeName Gear Type Name
     * @param float $replacementDistance Default Replacement Distance
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed> 
     */
    public function createGearType($gearTypeName, $replacementDistance=0)
    {
    	$parameters = array(
    			'gear_type_name' => $gearTypeName,
    			'replacement_distance' => $replacementDistance,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('gear/create_gear_type', 'GET', $parameters);
    }
    
    /**
     * This method deletes an Item of Gear for a User.
     * 
     * @throws Exception
     * @param int $gearId Gear ID
     * @param int $gearKey Gear Key
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function deleteGear($gearId=null, $gearKey=null)
    {
    	$parameters = array(
    			'gear_id' => $gearId,
    			'gear_key' => $gearKey,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('gear/delete_gear', 'GET', $parameters);
    }
    
    /**
     * This method returns a Gear Record
     *
     * @throws Exception
     * @param int $gearId Gear ID
     * @param int $gearKey Gear Key
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getGear($gearId=null, $gearKey=null)
    {
    	$parameters = array(
    			'gear_id' => $gearId,
    			'gear_key' => $gearKey,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('gear/get_gear', 'GET', $parameters);
    }
    
    /**
     * This method returns a name-value list of Gear for a User
     * 
     * @throws Exception
     * @param int $gearTypeId Gear Type ID
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getGearOptions($gearTypeId)
    {
    	$parameters = array(
    			'gear_type_id' => $gearTypeId,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('gear/get_gear_options', 'GET', $parameters);
    }
    
    /**
     * This method returns a name-value list of Gear Types for a User
     * 
     * @throws Exception
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getGearTypeOptions()
    {
    	$parameters = array(
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('gear/get_gear_type_options', 'GET', $parameters);
    }
    
    /**
     * This method returns a list of a user's gear
     * 
     * @throws Exception
     * @param int $gearTypeId Gear Type ID to search for
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getUserGear($gearTypeId=null)
    {
    	$parameters = array(
    			'o' => $this->responseFormat,
    			'gear_type_id' => $gearTypeId,
    	);
    	return $this->fetchResponse('gear/get_user_gear', 'GET', $parameters);
    }
    
    /**
     * This method checks if a Gear Brand is a duplicate.
     * 
     * @throws Exception
     * @param string $gearBrandName Brand Name
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function isDuplicateGearBrandName($gearBrandName)
    {
    	$parameters = array(
    			'o' => $this->responseFormat,
    			'gear_brand_name' => $gearBrandName,
    	);
    	return $this->fetchResponse('gear/is_duplicate_gear_brand_name', 'GET', $parameters);
    }
    
    /**
     * This method returns a custom list of suggested Gear Brands.
     * 
     * @throws Exception
     * @param int $gearTypeId Gear Type ID
     * @param string $q Search Query
     */
    public function suggestGearBrands($gearTypeId, $q)
    {
    	$parameters = array(
    			'o' => $this->responseFormat,
    			'gear_type_id' => $gearBrandName,
    			'q' => $q,
    	);
    	return $this->fetchResponse('gear/suggest_gear_brands', 'GET', $parameters);
    }
    
    /**
     * Groups
     */
    
    /**
     * This method deletes a Group.
     * 
     * @throws Exception
     * @param int $groupId Group ID
     * @param int $groupKey Group Key
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function deleteGroup($groupId=null, $groupKey=null)
    {
    	$parameters = array(
    			'group_id' => $groupId,
    			'group_key' => $groupKey,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('groups/delete_group', 'GET', $parameters);
    }
    
    /**
     * This method returns the Group Record
     *
     * @throws Exception
     * @param int $groupId Group ID
     * @param int $groupKey Group Key
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getGroup($groupId=null, $groupKey=null)
    {
    	$parameters = array(
    			'group_id' => $groupId,
    			'group_key' => $groupKey,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('groups/get_group', 'GET', $parameters);
    }
    
    /**
     * This method returns the Group Record
     * 
     * @throws Exception
     * @param int $groupId Group ID
     * @param int $groupKey Group Key
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getGroupStats($groupId=null, $groupKey=null)
    {
    	$parameters = array(
    			'group_id' => $groupId,
    			'group_key' => $groupKey,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('groups/get_group_stats', 'GET', $parameters);
    }
    
    /**
     * This method returns Groups Types
     * 
     * @throws Exception
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getGroupTypes()
    {
    	$parameters = array(
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('groups/get_group_types', 'GET', $parameters);
    }
    
    /**
     * This method joins a user to a group.
     * 
     * @throws Exception
     * @param int $groupId Group ID
     * @param int $groupKey Group Key
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function joinGroup($groupId=null, $groupKey=null)
    {
    	$parameters = array(
    			'group_id' => $groupId,
    			'group_key' => $groupKey,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('groups/join_group', 'GET', $parameters);
    }
    
    /**
     * This method allows a user to quit a group.
     * 
     * @throws Exception
     * @param int $groupId Group ID
     * @param int $groupKey Group Key
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function leaveGroup($groupId=null, $groupKey=null)
    {
    	$parameters = array(
    			'group_id' => $groupId,
    			'group_key' => $groupKey,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('groups/leave_group', 'GET', $parameters);
    }
    
    /**
     * This method creates a new Group. Authenticated User will be the Admin
     * 
     * @throws Exception
     * @param string $groupName Group Name
     * @param bool $publicFlag Is the Group Public?
     * @param string $groupContactPerson Contact Person
     * @param string  $webSite Web Site
     * @param array $additionalOptions  An array of additional, optional arguments, with key/values following the format outlined at {@link http://api.mapmyfitness.com/3.1/groups/update_group}
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function updateGroup($groupName, $publicFlag, $groupContactPerson, $webSite, $additionalOptions)
    {
    	$parameters = array(
    			'group_name' => $groupName,
    			'public_flag' => $publicFlag,
    			'group_contact_person' => $groupContactPerson,
    			'web_site' => $webSite,
    			'o' => $this->responseFormat,
    	);
    	$parameters = array_merge($parameters, $additionalOptions);
    	return $this->fetchResponse('groups/update_group', 'GET', $parameters);
    }
    
    /**
     * Resource
     */
    
    /**
     * Output Resource
     * 
     * @throws Exception
     * @param array $parameters An array of additional, optional arguments, with key/values following the format outlined at {@link http://api.mapmyfitness.com/3.1/resource/get_resource}
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getResource($parameters)
    {
    	return $this->fetchResponse('groups/update_group', 'GET', $parameters);
    }
    
    /**
     * Routes
     */
    
    /**
     * This method creates a private copy of a route to the authorized user's profile.
     * 
     * @throws Exception
     * @param int $routeId Route ID  
     * @param int $routeKey Route Key
     * @param string $r Old Route Key -- Depreciated Format (32 length varchar)
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function copyRoute($routeId=null, $routeKey=null, $r=null)
    {
    	$parameters = array(
    			'route_id' => $routeId,
    			'route_key' => $routeKey,
    			'r' => $r,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('routes/copy_route', 'GET', $parameters);
    }
    
    /**
     * This method saves (edit / create) a Route Record
     * 
     * @throws Exception
     * @param string $routeName Name of the Route
     * @param int $routeTypeId Route Type ID (see routes/get_route_types)
     * @param float $totalDistance Total Distance of the Route (miles) 
     * @param string $routeData The route data in the following format: lng1,lat1,[markerId],[timestamp],[notes]|lng2,lat2,[markerId],[order],[notes]...
     * @param array $additionalOptions An array of additional, optional arguments, with key/values following the format outlined at {@link http://api.mapmyfitness.com/3.1/routes/create_route}
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function createRoute($routeName, $routeTypeId, $totalDistance, $routeData, $additionalOptions)
    {
    	$parameters = array(
    			'route_name' => $routeName,
    			'route_type_id' => $routeTypeId,
    			'todal_distance' => $totalDistance,
    			'route_data' => $routeData,
    			'o' => $this->responseFormat,
    	);
    	$parameters = array_merge($parameters, $additionalOptions);
    	return $this->fetchResponse('routes/create_route', 'GET', $parameters);
    }
    
    /**
     * This method deletes a Route Record.
     *
     * @throws Exception
     * @param int $routeId Route ID
     * @param int $routeKey Route Key
     * @param string $r Old Route Key -- Depreciated Format (32 length varchar)
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function deleteRoute($routeId=null, $routeKey=null, $r=null)
    {
    	$parameters = array(
    			'route_id' => $routeId,
    			'route_key' => $routeKey,
    			'r' => $r,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('routes/delete_route', 'GET', $parameters);
    }
    
    /**
     * This method returns the Elevation For a Point
     * 
     * @throws Exception
     * @param string $latitude Latitude  
     * @param string $longitude Longitude
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getPointElevation($latitude, $longitude)
    {
    	$parameters = array(
    			'latitude' => $latitude,
    			'longitude' => $longitude,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('routes/get_point_elevation', 'GET', $parameters);
    }
    
    /**
     * This method returns the Route Record.
     * 
     * @throws Exception
     * @param int $routeId Route ID
     * @param int $routeKey Route Key
     * @param bit $createdDate Look up the created date of the route
     * @param bit $activityType Look up the activity type of the route
     * @param bit $oldJson Set to output a legacy-style JSON file for route data
     * @param int $loc request location information
     * @param string $r Old Route Key -- Depreciated Format (32 length varchar)
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getRoute($routeId=null, $routeKey=null, $createdDate=null, $activityType=null, $oldJson=null, $loc=null, $r=null)
    {
    	$parameters = array(
    			'route_id' => $routeId,
    			'route_key' => $routeKey,
    			'created_date' => $createdDate,
    			'activity_type' => $activityType,
    			'old_json' => $oldJson,
    			'loc' => $loc,
    			'r' => $r,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('routes/get_route', 'GET', $parameters);
    }
    
    /**
     * This method returns a the route climb data
     * 
     * @throws Exception
     * @param int $routeId Route ID
     * @param int $routeKey Route Key
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getRouteClimbData($routeId=null, $routeKey=null)
    {
    	$parameters = array(
    			'route_id' => $routeId,
    			'route_key' => $routeKey,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('routes/get_route_climb_data', 'GET', $parameters);
    }
    
    /**
     * This method returns the Route Data in Garmin CRS format.
     * 
     * @throws Exception
     * @param int $secondsPerMile Pace (Seconds / Mile)  
     * @param int $routeKey Route Key
     * @param string $fileExtension File Extension {crs, tcx} 
     * @param string $r Old Route Key -- Depreciated Format (32 length varchar)
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getRouteCrs($secondsPerMile, $routeKey=null, $fileExtension=null, $r=null)
    {
    	$parameters = array(
    			'seconds_per_mile' => $secondsPerMile,
    			'route_key' => $routeKey,
    			'file_extension' => $fileExtension,
    			'r' => $r,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('routes/get_route_crs', 'GET', $parameters);
    }
    
    /**
     * This method returns the Route Record.
     * 
     * @throws Exception
     * @param int $routeId Route ID
     * @param int $routeKey Route Key
     * @param bit $createdDate Look up the created date of the route
     * @param bit $activityType Look up the activity type of the route
     * @param bit $oldJson Set to output a legacy-style JSON file for route data
     * @param int $loc request location information
     * @param string $r Old Route Key -- Depreciated Format (32 length varchar)
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getRouteDistances($routeId=null, $routeKey=null, $createdDate=null, $activityType=null, $oldJson=null, $loc=null, $r=null)
    {
    	$parameters = array(
    			'route_id' => $routeId,
    			'route_key' => $routeKey,
    			'created_date' => $createdDate,
    			'activity_type' => $activityType,
    			'old_json' => $oldJson,
    			'loc' => $loc,
    			'r' => $r,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('routes/get_route_distances', 'GET', $parameters);
    }
    
    /**
     * This method returns a the route elevation summary data
     * 
     * @throws Exception
     * @param int $routeId Route ID
     * @param int $routeKey Route Key
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getRouteElevationSummary($routeId=null, $routeKey=null)
    {
    	$parameters = array(
    			'route_id' => $routeId,
    			'route_key' => $routeKey,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('routes/get_route_elevation_summary', 'GET', $parameters);
    }
    
    /**
     * This method returns the Route Data in *.GPX format. It has been updated to ignore floating marker types.
     *  
     * @throws Exception
     * @param int $routeId Route ID
     * @param int $routeKey Route Key
     * @param bit $elevationFlag Include Elevation? (meters) 1=yes 
     * @param string $r Old Route Key -- Depreciated Format (32 length varchar)
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getRouteGpx($routeId=null, $routeKey=null, $elevationFlag=null, $r=null)
    {
    	$parameters = array(
    			'route_id' => $routeId,
    			'route_key' => $routeKey,
    			'elevation_flag' => $elevationFlag,
    			'r' => $r,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('routes/get_route_gpx', 'GET', $parameters);
    }
    
    /**
     * This method returns a JSON containing the route data
     * 
     * @throws Exception
     * @param int $routeKey Route Key
     * @param string $r Old Route Key -- Depreciated Format (32 length varchar)
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getRouteJson($routeKey=null, $r=null)
    {
    	$parameters = array(
    			'route_key' => $routeKey,
    			'r' => $r,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('routes/get_route_json', 'GET', $parameters);
    }
    
    /**
     * This method returns the Route Data in *.KML format.
     * 
     * @throws Exception
     * @param int $routeKey Route Key
     * @param string $r Old Route Key -- Depreciated Format (32 length varchar)
     * @param array $additionalOptions An array of additional, optional arguments, with key/values following the format outlined at {@link http://api.mapmyfitness.com/3.1/routes/get_route_kml}
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getRouteKml($routeKey=null, $r=null, $additionalOptions = null)
    {
    	$parameters = array(
    			'route_key' => $routeKey,
    			'r' => $r,
    	);
    	$parameters = array_merge($parameters, $additionalOptions);
    	return $this->fetchResponse('routes/get_route_kml', 'GET', $parameters);
    }
    
	/**
     * This method returns the Route Data in tour-friendly kml.
     * 
     * @throws Exception
     * @param int $routeKey Route Key
     * @param string $r Old Route Key -- Depreciated Format (32 length varchar)
     * @param array $additionalOptions An array of additional, optional arguments, with key/values following the format outlined at {@link http://api.mapmyfitness.com/3.1/routes/get_route_kml_tour}
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getRouteKmlTour($routeKey=null, $r=null, $additionalOptions = null)
    {
    	$parameters = array(
    			'route_key' => $routeKey,
    			'r' => $r,
    	);
    	$parameters = array_merge($parameters, $additionalOptions);
    	return $this->fetchResponse('routes/get_route_kml_tour', 'GET', $parameters);
    }
    
    /**
     * This method allows you to output route points in CSV file.
     * 
     * @throws Exception
     * @param int $userId User ID
     * @param int $userKey User Key 
     * @param array $additionalOptions An array of additional, optional arguments, with key/values following the format outlined at {@link http://api.mapmyfitness.com/3.1/routes/get_route_points_csv}
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getRoutePointsCsv($userId = null, $userKey = null, $additionalOptions)
    {
    	$parameters = array(
    			'user_id' => $userId,
    			'user_key' => $userKey,
    			'o' => $this->responseFormat,
    	);
    	$parameters = array_merge($parameters, $additionalOptions);
    	return $this->fetchResponse('routes/get_route_points_csv', 'GET', $parameters);
    }
    
    /**
     * This method allows you to get route points in KML file.
     * 
     * @throws Exception
     * @param int $userId User ID
     * @param int $userKey User Key
     * @param array $additionalOptions An array of additional, optional arguments, with key/values following the format outlined at {@link http://api.mapmyfitness.com/3.1/routes/get_route_points_kml}
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getRoutePointsKml($userId = null, $userKey = null, $additionalOptions)
    {
    	$parameters = array(
    			'user_id' => $userId,
    			'user_key' => $userKey,
    			'o' => $this->responseFormat,
    	);
    	$parameters = array_merge($parameters, $additionalOptions);
    	return $this->fetchResponse('routes/get_route_points_kml', 'GET', $parameters);
    }
    
    /**
     * This method allows you to get route locations according to passed username & md5 password combo
     * 
     * @throws Exception
     * @param int $userId User id
     * @param int $userKey User Key
     * @param int $routeTypeId Choose Routes to Display by Route Type
     * @param int $limit Number of results to return, 50 maximum
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getRouteStartLocations($userId=null, $userKey=null, $routeTypeId=null, $limit=null)
    {
    	$parameters = array(
    			'user_id' => $userId,
    			'user_key' => $userKey,
    			'route_type_id' => $routeTypeId,
    			'limit' => $limit,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('routes/get_route_start_locations', 'GET', $parameters);
    }
    
    /**
     * This method returns Route Types
     * 
     * @throws Exception
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */  
    public function getRouteTypes()
    {
    	return $this->fetchResponse('routes/get_route_types', 'GET', array('o' => $this->responseFormat));
    }
    
    /**
     * This method allows you to get recent routes according to passed username & md5 password combo
     *
     * @throws Exception
     * @param int $userId User ID
     * @param int $userKey User Key
     * @param array $additionalOptions An array of additional, optional arguments, with key/values following the format outlined at {@link http://api.mapmyfitness.com/3.1/routes/get_routes}
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getRoutes($userId = null, $userKey = null, $additionalOptions)
    {
    	$parameters = array(
    			'user_id' => $userId,
    			'user_key' => $userKey,
    			'o' => $this->responseFormat,
    	);
    	$parameters = array_merge($parameters, $additionalOptions);
    	return $this->fetchResponse('routes/get_routes', 'GET', $parameters);
    }
    
    /**
     * This method takes in a data file and outputs _________.
     * 
     * @throws Exception
     * @param file $file Data File
     * @param bool $test Test Flag: if test=1 then the page will display a test form.  
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function importRoute($file, $test)
    {
    	$parameters = array(
    			'file' => $file,
    			'test' => $test,
    	);
    	return $this->fetchResponse('routes/import_route', 'GET', $parameters);
    }
    
    /**
     * This method saves (edit / create) a Route Record
     *  
     * @throws Exception
     * @param string $routeName Name of the Route
     * @param int $routeTypeId Route Type ID (see routes/get_route_types)
     * @param float $totalDistance Total Distance of the Route (miles)
     * @param string $routeData The route data in the following format: lng1,lat1,[markerId],[timestamp],[notes]|lng2,lat2,[markerId],[order],[notes]...( show marker ids)
     * @param array $additionalOptions An array of additional, optional arguments, with key/values following the format outlined at {@link http://api.mapmyfitness.com/3.1/routes/save_routes}
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function saveRoute($routeName, $routeTypeId, $totalDistance, $routeData, $additionalOptions)
    {
    	$parameters = array(
    			'route_name' => $routeName,
    			'route_type_id' => $routeTypeId,
    			'total_distance' => $totalDistance,
    			'route_data' => $routeData,
    			'o' => $this->responseFormat,
    	);
    	$parameters = array_merge($parameters, $additionalOptions);
    	return $this->fetchResponse('routes/save_route', 'GET', $parameters);
    }
    
    /**
     * This method saves (edit / create) a Route Record
     * 
     * @throws Exception
     * @param string $routeName Name of the Route
     * @param int $routeTypeId Route Type ID (see routes/get_route_types)
     * @param string $city Starting City for the Route
     * @param string $country Country Code (2 char)
     * @param float $totalDistance Total Distance of the Route (miles)
     * @param string $routeData The route data in the following format: lng1,lat1,[markerId],[timestamp],[notes]|lng2,lat2,[markerId],[order],[notes]...( show marker ids)
     * @param array $additionalOptions An array of additional, optional arguments, with key/values following the format outlined at {@link http://api.mapmyfitness.com/3.1/routes/save_routes}
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function saveRoute2($routeName, $routeTypeId, $city, $country, $totalDistance, $routeData, $additionalOptions)
    {
    	$parameters = array(
    			'route_name' => $routeName,
    			'route_type_id' => $routeTypeId,
    			'city' => $city,
    			'country' => $country,
    			'total_distance' => $totalDistance,
    			'route_data' => $routeData,
    			'o' => $this->responseFormat,
    	);
    	$parameters = array_merge($parameters, $additionalOptions);
    	return $this->fetchResponse('routes/save_route2', 'GET', $parameters);
    }
    
    /**
     * This method allows you to search for top routes by numerous criteria
     * 
     * @throws Exception
     * @param int $userId User ID
     * @param int $userKey User Key
     * @param array $additionalOptions An array of additional, optional arguments, with key/values following the format outlined at {@link http://api.mapmyfitness.com/3.1/routes/search_routes}
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function searchRoutes($userId = null, $userKey = null, $additionalOptions)
    {
    	$parameters = array(
    			'user_id' => $userId,
    			'user_key' => $userKey,
    			'o' => $this->responseFormat,
    	);
    	$parameters = array_merge($parameters, $additionalOptions);
    	return $this->fetchResponse('routes/search_routes', 'GET', $parameters);
    }
    
    /**
     * This method allows users / non-users to email a route to an email list.
     * 
     * @throws Exception
     * @param string $emailAddressList List of comma-separated email addresses
     * @param int $routeId Route ID
     * @param string $routeKey Route Key
     * @param string $r Old Route Key -- Depreciated Format (32 length varchar) 
     * @param array $additionalOptions An array of additional, optional arguments, with key/values following the format outlined at {@link http://api.mapmyfitness.com/3.1/routes/share_route}
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function shareRoute($emailAddressList,$routeId=null, $routeKey=null, $r=null, $additionalOptions)
    {
    	$parameters = array(
    			'email_address_list' => $emailAddressList,
    			'route_id' => $routeId,
    			'route_key' => $routeKey,
    			'r' => $r,
    			'o' => $this->responseFormat,
    	);
    	$parameters = array_merge($parameters, $additionalOptions);
    	return $this->fetchResponse('routes/share_route', 'GET', $parameters);
    }
    
    /**
     * This method returns the Map Marker image (GIF).
     * 
     * @throws Exception
     * @param int $markerTypeId Marker Type ID 
     * @param int $txt Distance Marker Text
     * @param string $mode Specify gif if necessary.
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function viewMarkerImage($markerTypeId, $txt, $mode)
    {
    	$parameters = array(
    			'marker_type_id' => $markerTypeId,
    			'txt' => $txt,
    			'mode' => $mode,
    	);
    	return $this->fetchResponse('routes/view_marker_image', 'GET', $parameters);
    }
    
    /**
     * This method returns the Elevation Profile of a Route (img, json, csv).
     * 
     * @throws Exception
     * @param int $routeId Route ID
     * @param string $routeKey Route Key
     * @param string $r Old Route Key -- Depreciated Format (32 length varchar) 
     * @param array $additionalOptions An array of additional, optional arguments, with key/values following the format outlined at {@link http://api.mapmyfitness.com/3.1/routes/view_route_elevation}
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function viewRouteElevation($routeId=null, $routeKey=null, $r=null, $additionalOptions)
    {
    	$parameters = array(
    			'route_id' => $routeId,
    			'route_key' => $routeKey,
    			'r' => $r,
    			'o' => $this->responseFormat,
    	);
    	$parameters = array_merge($parameters, $additionalOptions);
    	return $this->fetchResponse('routes/view_route_elevation', 'GET', $parameters);
    }
    
    /**
     * This method returns the Image Tile (PNG) of a Route.
     * 
     * @throws Exception
     * @param int $routeId Route ID
     * @param string $routeKey Route Key
     * @param string $r Old Route Key -- Depreciated Format (32 length varchar) 
     * @param array $additionalOptions An array of additional, optional arguments, with key/values following the format outlined at {@link http://api.mapmyfitness.com/3.1/routes/view_route_image}
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function viewRouteImage($routeId=null, $routeKey=null, $r=null, $additionalOptions)
    {
    	$parameters = array(
    			'route_id' => $routeId,
    			'route_key' => $routeKey,
    			'r' => $r,
    			'o' => $this->responseFormat,
    	);
    	$parameters = array_merge($parameters, $additionalOptions);
    	return $this->fetchResponse('routes/view_route_image', 'GET', $parameters);
    }
    
    /**
     * Users
     */
    
    /**
     * This method makes a user a friend of the authorized user.
     * 
     * @throws Exception
     * @param int $friendUserKey User Key of New Friend 
     * @param int $friendUserId User ID of New Friend  
     * @param string $friendEmail Email Address of Friend 
     * @param string $source Source of Friend
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function addFriend($friendUserKey=null, $friendUserId=null, $friendEmail=null, $source=null)
    {
    	$parameters = array(
    			'friend_user_key' => $friendUserKey,
    			'friend_user_id' => $friendUserId,
    			'friend_email' => $friendEmail,
    			'source' => $source,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('users/add_friend', 'GET', $parameters);
    }
    
    /**
     * This method adds a friend to the authorized user's friend list. The friend can be looked up by user_id/user_key or friend_id/friend_key.
     * 
     * @throws Exception
     * @param int $friendUserKey User Key of Friend
     * @param int $friendUserId User ID of Friend
     * @param int $friendKey Friend Key of Friend
     * @param int $friendId Friend ID of Friend
     * @param int $friendListId Friend List ID
     * @param int $friendListKey Friend List Key
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function addFriendToFriendList($friendUserKey=null, $friendUserId=null, $friendKey=null, $friendId=null, $friendListId=null, $friendListKey=null)
    {
    	$parameters = array(
    			'friend_user_key' => $friendUserKey,
    			'friend_user_id' => $friendUserId,
    			'friend_key' => $friendKey,
    			'firend_id' => $friendId,
    			'friend_list_id' => $friendListId,
    			'friend_list_key' => $friendListKey,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('users/add_friend_to_friend_list', 'GET', $parameters);
    }
    
    /**
     * This method creates a user:remote user relationship.
     * 
     * @throws Exception
     * @param int $remoteUserTypeId Remote User Type ID (Facebook = 1, Yahoo = 3)
     * @param string $remoteId ID of the Remote User
     * @param array $additionalOptions An array of additional, optional arguments, with key/values following the format outlined at {@link http://api.mapmyfitness.com/3.1/users/associate_remote_user}
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function associateRemoteUser($remoteUserTypeId, $remoteId, $additionalOptions)
    {
    	$parameters = array(
    			'remote_user_type_id' => $remoteUserTypeId,
    			'remote_id' => $remoteId,
    			'o' => $this->responseFormat,
    	);
    	$parameters = array_merge($parameters, $additionalOptions);
    	return $this->fetchResponse('users/associate_remote_user', 'GET', $parameters);
    }
    
    /**
     * This method returns User ID if a valid User is based using BASIC AUTHENTICATION.
     * 
     * @throws Exception
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function authenticateUser()
    {
    	return $this->fetchResponse('users/authenticate_user', 'GET', 
    			array('o' => $this->responseFormat,));
    }
    
    /**
     * This method checks if User is authorized to run the BlackBerry application.
     * 
     * @throws Exception
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function checkBbAuth()
    {
    	return $this->fetchResponse('users/check_bb_auth', 'GET',
    			array('o' => $this->responseFormat,));
    }
    
    /**
     * This method creates a friend list for authorized user.
     * 
     * @throws Exception
     * @param string $friendListName Friend List Name
     * @param string $friendListDescription Friend List Description
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function createFriendList($friendListName, $friendListDescription=null)
    {
    	$parameters = array(
    			'friend_list_name' => $friendListName,
    			'friend_list_description' => $friendListDescription,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('users/create_friend_list', 'GET', $parameters);
    }
    
    /**
     * This method creates a User Record.
     * 
     * @throws Exception
     * @param int $remoteUserTypeId Remote User Type ID (Facebook = 1, Yahoo = 2)
     * @param string $remoteId ID of the Remote User
     * @param string $firstName First Name
     * @param string $username Username
     * @param string $email Email
     * @param string $password Password
     * @param bit $promoOptOutFlag Gear Deals / Promotional Opt Out Flag
     * @param array $additionalOptions An array of additional, optional arguments, with key/values following the format outlined at {@link http://api.mapmyfitness.com/3.1/users/create_remote_user}
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function createRemoteUser($remoteUserTypeId, $remoteId, $firstName, $username, $email, $password, $promoOptOutFlag, $additionalOptions)
    {
    	$parameters = array(
    			'remote_user_type_id' => $remoteUserTypeId,
    			'remote_id' => $remoteId,
    			'first_name' => $firstName,
    			'username' => $username,
    			'email' => $email,
    			'password' => $password,
    			'promo_opt_out_flag' => $promoOptOutFlag,
    			'o' => $this->responseFormat,
    	);
    	$parameters = array_merge($parameters, $additionalOptions);
    	return $this->fetchResponse('users/create_remote_user', 'GET', $parameters);
    }
    
    /**
     * This method creates a User Record.
     * 
     * @throws Exception
     * @param string $firstName First Name
     * @param string $username Username
     * @param string $email Email
     * @param string $password Password
     * @param bit $promoOptOutFlag Gear Deals / Promotional Opt Out Flag
     * @param array $additionalOptions An array of additional, optional arguments, with key/values following the format outlined at {@link http://api.mapmyfitness.com/3.1/users/create_user}
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function createUser($firstName, $username, $email, $password, $promoOptOutFlag, $additionalOptions)
    {
    	$parameters = array(
    			'first_name' => $firstName,
    			'username' => $username,
    			'email' => $email,
    			'password' => $password,
    			'promo_opt_out_flag' => $promoOptOutFlag,
    			'o' => $this->responseFormat,
    	);
    	$parameters = array_merge($parameters, $additionalOptions);
    	return $this->fetchResponse('users/create_user', 'GET', $parameters);
    }
    
    /**
     * This method deletes a friend list for authorized user.
     * 
     * @throws Exception
     * @param int $friendListId
     * @param int $friendListKey
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function deleteFriendList($friendListId=null, $friendListKey=null)
    {
    	$parameters = array(
    			'first_list_id' => $firstName,
    			'friend_list_key' => $username,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('users/delete_friend_list', 'GET', $parameters);
    }
    
    /**
     * This method deletes a user:remote user relationship.
     * 
     * @throws Exception
     * @param int $remoteUserTypeId Remote User Type ID (Facebook = 1, Yahoo = 2)
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function deleteRemoteUser($remoteUserTypeId)
    {
    	$parameters = array(
    			'remote_user_type_id' => $remoteUserTypeId,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('users/delete_remote_user', 'GET', $parameters);
    }
    
    /**
     * Output Resource
     * 
     * @throws Exception
     * @param string $size
     * @param int $uid
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getAvatar($size=null, $uid=null)
    {
    	$parameters = array(
    			'size' => $size,
    			'uid' => $uid,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('users/get_avatar', 'GET', $parameters);
    }
    
    /**
     * This method returns the fundraising progress for a user or campaign ID.
     * 
     * @throws Exception
     * @param int $userId User ID
     * @param int $userKey User Key
     * @param int $campaignId MGive Campaign ID
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getFundraisingStats($userId=null, $userKey=null, $campaignId=null)
    {
    	$parameters = array(
    			'user_id' => $userId,
    			'user_key' => $userKey,
    			'campaign_id' => $campaignId,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('users/get_fundraising_stats', 'GET', $parameters);
    }
    
    /**
     * This method returns the Friend Lists of the Authorized User.
     * 
     * @throws Exception
     * @param int $messageKey
     * @param int $messageId
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getMessage($messageKey=null, $messageId=null)
    {
    	$parameters = array(
    			'message_key' => $messageKey,
    			'message_id' => $messageId,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('users/get_message', 'GET', $parameters);
    }
    
    /**
     * This method returns remote user types
     * 
     * @throws Exception
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getRemoteUserTypes()
    {
    	return $this->fetchResponse('users/get_remote_user_types', 'GET', 
    			array('o' => $this->responseFormat));
    }
    
    /**
     * This method returns the User record. Defaults to requesting user if user_id/user_key not passed
     * 
     * @throws Exception
     * @param int $userId User ID
     * @param int $userKey User Key
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getUser($userId=null, $userKey=null)
    {
    	$parameters = array(
    			'user_id' => $userId,
    			'user_key' => $userKey,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('users/get_user', 'GET', $parameters);
    }
    
    /**
     * This method returns an array of Contacts for a user given a remote email and password. (Aol, Yahoo, Gmail, Hotmail)
     *  
     * @throws Exception
     * @param string $username
     * @param string $domain
     * @param string $password
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getUserContacts($username, $domain, $password)
    {
    	$parameters = array(
    			'username' => $userId,
    			'domain' => $userKey,
    			'password' => $password,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('users/get_user_contacts', 'GET', $parameters);
    }
    
    /**
     * This method returns the Friend Lists of the Authorized User.
     * 
     * @throws Exception
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getUserFriendList()
    {
    	return $this->fetchResponse('users/get_user_friend_list', 'GET',
    			array('o' => $this->responseFormat,));
    }
    
    /**
     * This method returns the name-value list of the Friend Lists for a User.
     * 
     * @throws Exception
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getUserFriendListOptions()
    {
    	return $this->fetchResponse('users/get_user_friend_list_options', 'GET',
    			array('o' => $this->responseFormat,));
    }
    
    /**
     * This method returns an array of Friend Requests for the authenticated User.
     * 
     * @throws Exception
     * @param string $status
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getUserFriendRequests($status=null)
    {
    	$parameters = array(
    			'status' => $status,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('users/get_user_friend_requests', 'GET', $parameters);
    }
    
    /**
     * This method returns an array of Users who are Friends of the authenticated User.
     *
     * @throws Exception
     * @param int $friendListId Friend List ID
     * @param int $friendListKey Friend List Key
     * @param array $additionalOptions An array of additional, optional arguments, with key/values following the format outlined at {@link http://api.mapmyfitness.com/3.1/users/get_user_friends}
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getUserFriends($friendListId=null, $friendListKey=null, $additionalOptions)
    {
    	$parameters = array(
    			'friend_list_id' => $friendListId,
    			'friend_list_key' => $friendListKey,
    			'o' => $this->responseFormat,
    	);
    	$parameters = array_merge($parameters, $additionalOptions);
    	return $this->fetchResponse('users/get_user_friends', 'GET', $parameters);
    }
    
    /**
     * This method returns all User Keys matching an Email Address
     * 
     * @throws Exception
     * @param string $email Email Address
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getUserKeys($email)
    {
    	$parameters = array(
    			'email' => $email,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('users/get_user_keys', 'GET', $parameters);
    }
    
    /**
     * This method returns user locations.
     * 
     * @throws Exception
     * @param int $minutes Get the interval of users prior to now (in minutes -- ie = 5 gets all users created last 5 minutes)
     * @param int $siteId Site ID
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getUserLocations($minutes = null, $siteId = null)
    {
    	$parameters = array(
    			'minutes' => $minutes,
    			'site_id' => $siteId,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('users/get_user_locations', 'GET', $parameters);
    }
    
    /**
     * This method returns the Friend Lists of the Authorized User.
     * 
     * @throws Exception
     * @param string $status Friend Request Status (all, archived, sent, read, unread)
     * @param int $startRecord Index of first result to return. E.g., when selecting 25 results at a time, set start to 0 for the first request, and to 25 for the second  
     * @param int $limit Number of results to return (100 max) 
     * @param string $sortBy Comma-delimited list of properties to sort on and the direction of sorting (asc or desc). Possible sorts include: username sent_date subject
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getUserMessages($status = null, $startRecord = null, $limit = null, $sortBy = null)
    {
    	$parameters = array(
    			'status' => $status,
    			'start_record' => $startRecord,
    			'limit' => $limit,
    			'sort_by' => $sortBy,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('users/get_user_messages', 'GET', $parameters);
    }
    
    /**
     * This method returns the User stats. Defaults to requesting user if user_id/user_key not passed.
     * 
     * @throws Exception
     * @param int $userId User ID
     * @param int $userKey User Key
     * @param string $parentWorkoutTypeIds Filter by Parent Workout Type IDs - Comma Delimited List (see workouts/get_parent_workout_types)
     * @param string $dateFrom From Date (YYYY-MM-DD)
     * @param string $dateTo To Date (YYYY-MM-DD)
     * @param string $period Group data into buckets {all, daily, weekly, weekly2, monthly, yearly}. Weekly 2 is monday to sunday 
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getUserStats($userId = null, $userKey = null, $parentWorkoutTypeIds = null, $dateFrom = null, $dateTo = null, $period = null)
    {
    	$parameters = array(
    			'user_id' => $userId,
    			'user_key' => $userKey,
    			'parent_workout_type_ids' => $parentWorkoutTypeIds,
    			'date_from' => $dateFrom,
    			'date_to' => $dateTo,
    			'period' => $period,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('users/get_user_stats', 'GET', $parameters);
    }
    
    /**
     * This method returns the User record. Defaults to requesting user if user_id/user_key not passed.
     * 
     * @throws Exception
     * @param int $userId User ID
     * @param int $userKey User Key
     * @param bit $fundraising Return fundraising information? (Default no) This will return fundraising-campaign-specific fields if available: tdf_participant (bool), mgive_fundraiser_id (int), mgive_campaign_id (int), mgive_keyword (string), mgive_shortcode (string)
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getUserSummary($userId = null, $userKey = null, $fundraising = null)
    {
    	$parameters = array(
    			'user_id' => $userId,
    			'user_key' => $userKey,
    			'fundraising' => $fundraising,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('users/get_user_summary', 'GET', $parameters);
    }
    
    /**
     * This method sends a site invitation to a list of email addresses. If the email is a user, it sends a friend request.
     * 
     * @throws Exception
     * @param string $emailList List of Comma-Delimited Email Addresses. If not provided, will use the authenticated user.  
     * @param string $product Product name (i.e. iMapMyRun, iMapMyRun+, etc.) 
     * @param string $siteName Site to promote  
     * @param string $message Optional Message
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function invite($emailList, $product, $siteName = null, $message)
    {
    	$parameters = array(
    			'email_list' => $emailList,
    			'product' => $product,
    			'site_name' => $siteName,
    			'message' => $message,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('users/invite', 'GET', $parameters);
    }
    
    /**
     * This method checks if an Email Address is a duplicate
     * 
     * @throws Exception
     * @param string $email Email Address
     * @param string $txtEmail Email Address (for Form Validation)
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function isDuplicateEmail($email = null, $txtEmail = null)
    {
    	$parameters = array(
    			'email' => $email,
    			'txtEmail' => $txtEmail,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('users/is_duplicate_email', 'GET', $parameters);
    }
    
    /**
     * This method checks if a Username is a duplicate
     * 
     * @throws Exception
     * @param string $username Username
     * @param string $txtUsername Username (for Form Validation)
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function isDuplicateUsername($username = null, $txtUsername = null)
    {
    	$parameters = array(
    			'username' => $username,
    			'txtUsername' => $txtUsername,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('users/is_duplicate_username', 'GET', $parameters);
    }
    
    /**
     * This method checks if User ia a premium User.
     * 
     * @throws Exception
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function isPremiumUser()
    {
    	return $this->fetchResponse('users/is_premium_user', 'GET', array('o' => $this->responseFormat));
    }
    
    /**
     * This method checks to see if the Remote User exists in our system.
     * 
     * @throws Exception
     * @param int $remoteUserTypeId Remote User Type ID (Facebook = 1, Yahoo = 2)
     * @param string $remoteId ID of the Remote User
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function isRemoteUser($remoteUserTypeId, $remoteId)
    {
    	$parameters = array(
    			'remote_user_type_id' => $remoteUserTypeId,
    			'remote_id' => $remoteId,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('users/is_remote_user', 'GET', $parameters);
    }
    
    /**
     * This method checks to see if User is a valid User.
     * 
     * @throws Exception
     * @param string $emailList List of Comma-Delimited Email Addresses. If not provided, will use the authenticated user.
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function isValidUser($emailList = null)
    {
    	$parameters = array(
    		'email_list' => $emailList,
    		'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('users/is_valid_user', 'GET', $parameters);
    }
    
    /**
     * This method returns the Logged in User Record
     * 
     * @throws Exception
     * @param string $u Username or Email Address
     * @param string $p Password or MD5 Password
     * @param int $userKey user_key of user to log in
     * @param bool $logout Set to true to log user out
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function login($u = null, $p = null, $userKey = null, $logout =null)
    {
    	$parameters = array(
    		'u' => $u,
    		'p' => $p,
    		'user_key' => $userKey,
    		'logout' => $logout,
    		'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('users/login', 'GET', $parameters);
    }
    
    /**
     * This approves or igores (denies) friend requests for an authenticated User.
     * 
     * @throws Exception
     * @param string $processRequest Process (approve, ignore)
     * @param string $friendUserKey User Key of New Friend 
     * @param int $friendUserId User ID of New Friend
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function processFriendTequests($processRequest, $friendUserKey = null, $friendUserId = null)
    {
    	$parameters = array(
    			'process_request' => $processRequest,
    			'friend_user_key' => $friendUserKey,
    			'friend_user_id' => $friendUserId,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('users/process_friend_requests', 'GET', $parameters);
    }
    
    /**
     * This method removes a friend of the authorized user.
     * 
     * @throws Exception
     * @param int $friendUserKey User Key of New Friend
     * @param int $friendUserId User ID of New Friend 
     * @param int $friendId Friend ID
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function removeFriend($friendUserKey = null, $friendUserId = null, $friendId = null)
    {
    	$parameters = array(
    			'friend_user_key' => $friendUserKey,
    			'friend_user_id' => $friendUserId,
    			'friend_id' => $friendId,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('users/remove_friend', 'GET', $parameters);
    }
    
    /**
     * This method removes a friend from the authorized user's friend list. The friend can be looked up by user_id/user_key or friend_id/friend_key.
     * 
     * @throws Exception
     * @param int $friendUserKey
     * @param int $friendUserId
     * @param array $additionalOptions An array of additional, optional arguments, with key/values following the format outlined at {@link http://api.mapmyfitness.com/3.1/users/remove_friend_from_friend_list}
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function removeFriendFromFriendList($friendUserKey = null, $friendUserId = null, $additionalOptions)
    {
    	$parameters = array(
    			'friend_user_key' => $friendUserKey,
    			'friend_user_id' => $friendUserId,
    			'o' => $this->responseFormat,
    	);
    	$parameters = array_merge($parameters, $additionalOptions);
    	return $this->fetchResponse('users/remove_friend_from_friend_list', 'GET', $parameters);
    }
    
    /**
     * This method allows you to search for users by numerous criteria
     * 
     * @throws Exception
     * @param string $keyword Searches the users by full name (first + ' ' + last) or username/profile  
     * @param array $additionalOptions An array of additional, optional arguments, with key/values following the format outlined at {@link http://api.mapmyfitness.com/3.1/users/search_users}
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function searchUsers($keyword = null, $additionalOptions)
    {
    	$parameters = array(
    			'keyword' => $keyword,
    			'o' => $this->responseFormat,
    	);
    	$parameters = array_merge($parameters, $additionalOptions);
    	return $this->fetchResponse('users/search_users', 'GET', $parameters);
    }
    
    /**
     * This method sends a friend request from the authorized user
     * 
     * @throws Exception
     * @param int $friendUserKey User Key of New Friend
     * @param int $friendUserId User ID of New Friend
     * @param string $message Optional Message
     * @param string $product Product name (i.e. iMapMyRun, iMapMyRun+, etc.)
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function sendFriendRequest($friendUserKey = null, $friendUserId = null, $message = null, $product = null)
    {
    	$parameters = array(
    			'friend_user_key' => $friendUserKey,
    			'friend_user_id' => $friendUserId,
    			'message' => $message,
    			'product' => $product,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('users/send_friend_request', 'GET', $parameters);
    }
    
    /**
     * This method allows a user to send a private message to another user
     * 
     * @throws Exception
     * @param string $subject Message Subject
     * @param string $message Message Text
     * @param int $userKey User Key to Send To
     * @param int $userId User ID to Send To
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function sendMessage($subject, $message, $userKey, $userId)
    {
    	$parameters = array(
    			'subject' => $subject,
    			'message' => $message,
    			'user_key' => $userKey,
    			'user_id' => $userId,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('users/send_message', 'GET', $parameters);
    }
    
    public function sendgrid_integrate()
    {
    	
    }
    
    /**
     * This method returns the tell a friend email content.
     * 
     * @throws Exception
     * @param string $product Product name (i.e. iMapMyRun)
     * @param string $siteName Site to promote
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function tellFriendContent($product, $siteName = null)
    {
    	$parameters = array(
    			'product' => $product,
    			'site_name' => $siteName,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('users/tell_friend_content', 'GET', $parameters);
    }
    
    /**
     * This method update a friend list for authorized user.
     * 
     * @throws Exception
     * @param string $friendListName Friend List Name
     * @param int $friendListId Friend List ID
     * @param int $friendListKey Friend List Key
     * @param string $friendListDescription Friend List Description 
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function updateFriendList($friendListName, $friendListId = null, $friendListKey = null, $friendListDescription = null)
    {
    	$parameters = array(
    			'friend_list_name' => $friendListName,
    			'friend_list_id' => $friendListId,
    			'friend_list_key' => $friendListKey,
    			'friend_list_description' => $friendListDescription,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('users/update_friend_list', 'GET', $parameters);
    }
    
    /**
     * This method updates a User Record. It only updates the fields you pass in.
     * 
     * @throws Exception
     * @param int $userId User ID (lookup only)
     * @param int $userKey User Key (lookup only)
     * @param array $additionalOptions An array of additional, optional arguments, with key/values following the format outlined at {@link http://api.mapmyfitness.com/3.1/users/update_user}
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function updateUser($userId = null, $userKey = null, $additionalOptions)
    {
    	$parameters = array(
    			'user_id' => $userId,
    			'user_key' => $userKey,
    			'o' => $this->responseFormat,
    	);
    	$parameters = array_merge($parameters, $additionalOptions);
    	return $this->fetchResponse('users/update_user', 'GET', $parameters);
    }
    
    /**
     * This method updates the User's Map Settings.
     * 
     * @throws Exception
     * @param string $mapSettings Map Settings JSON from the Route Engine
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function updateUserMapSettings($mapSettings)
    {
    	$parameters = array(
    			'map_settings' => $mapSettings,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('users/update_user_map_settings', 'GET', $parameters);
    }
    
    /**
     * This method checks if a User owns (has paid for) a specific Content Item.
     * 
     * @throws Exception
     * @param int $contentId Content ID 
     * @param int $contentTypeId Content Type ID 
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function userOwnsContent($contentId, $contentTypeId)
    {
    	$parameters = array(
    			'content_id' => $contentId,
    			'content_type_id' => $contentTypeId,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('users/user_owns_content', 'GET', $parameters);
    }
    
    /**
     * This method returns the User record.
     * 
     * @throws Exception
     * @param bit $fundraising Return fundraising information? (Default no) This will return fundraising-campaign-specific fields.
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function userSummary($fundraising = null)
    {
    	$parameters = array(
    			'fundraising' => $fundraising,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('users/user_summary', 'GET', $parameters);
    }
    
    /**
     * Workouts
     */
    
    /**
     * This method adds the provided time series data to the specified workout
     * 
     * @throws Exception
     * @param string $data JSON time series data
     * @param int $workoutId workout_id  
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function addTimeSeries($data, $workoutId = null)
    {
    	$parameters = array(
    			'data' => $data,
    			'workout_id' => $workoutId,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('workouts/add_time_series', 'GET', $parameters);
    }
    
    /**
     * This method creates a Workout Track / Lap Data for a Workout if the workout has a data file (tcx,gpx) in the ISDS.
     * 
     * @throws Exception
     * @param float $lapInterval Lap Interval in Miles
     * @param int $workoutId Workout ID
     * @param int $workoutKey Workout Key
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function convertIsdsToWorkoutTrack($lapInterval, $workoutId = null, $workoutKey = null)
    {
    	$parameters = array(
    			'lap_interval' => $lapInterval,
    			'workout_id' => $workoutId,
    			'workout_key' => $workoutKey,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('workouts/convert_isds_to_workout_track', 'GET', $parameters);
    }
    
    /**
     * This method creates a Workout Track / Lap Data for a Workout if the Route JSON has time series saved.
     *
     * @throws Exception
     * @param float $lapInterval Lap Interval in Miles
     * @param int $workoutId Workout ID
     * @param int $workoutKey Workout Key
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function convertRouteToWorkoutTrack($lapInterval, $workoutId = null, $workoutKey = null)
    {
    	$parameters = array(
    			'lap_interval' => $lapInterval,
    			'workout_id' => $workoutId,
    			'workout_key' => $workoutKey,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('workouts/convert_route_to_workout_track', 'GET', $parameters);
    }
    
    /**
     * This method creates a User Workout
     * 
     * @throws Exception
     * @param string $workoutDate Workout Date (YYYY-MM-DD)
     * @param string $workoutDescription Workout Description
     * @param array $additionalOptions An array of additional, optional arguments, with key/values following the format outlined at {@link http://api.mapmyfitness.com/3.1/workouts/create_workout}
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function createWorkout($workoutDate, $workoutDescription, $additionalOptions)
    {
    	$parameters = array(
    			'workout_date' => $workoutDate,
    			'workout_description' => $workoutDescription,
    			'o' => $this->responseFormat,
    	);
    	$parameters = array_merge($parameters, $additionalOptions);
    	return $this->fetchResponse('workouts/create_workout', 'GET', $parameters);
    }
    
    /**
     * This method creates a User Workout
     * 
     * @throws Exception
     * @param int $workoutId Workout Id
     * @param string $workoutTrackName Workout Track Name (This is An Internal Identifier, Not a Workout Name)
     * @param string $seconds String Delimited List of Point Corresponding Times
     * @param array $additionalOptions An array of additional, optional arguments, with key/values following the format outlined at {@link http://api.mapmyfitness.com/3.1/workouts/create_workout_track}
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function createWorkoutTrack($workoutId, $workoutTrackName, $seconds, $additionalOptions)
    {
    	$parameters = array(
    			'workout_id' => $workoutId,
    			'workout_track_name' => $workoutTrackName,
    			'seconds' => $seconds,
    			'o' => $this->responseFormat,
    	);
    	$parameters = array_merge($parameters, $additionalOptions);
    	return $this->fetchResponse('workouts/create_workout_track', 'GET', $parameters);
    }
    
    /**
     * This method creates a custom User Workout Type
     * 
     * @throws Exception
     * @param string $workoutTypeName Workout Type Name
     * @param int $parentWorkoutTypeId Parent Workout Type ID (see workouts/get_parent_workout_types) 
     * @param array $additionalOptions An array of additional, optional arguments, with key/values following the format outlined at {@link http://api.mapmyfitness.com/3.1/workouts/create_workout_type}
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function createWorkoutType($workoutTypeName, $parentWorkoutTypeId, $additionalOptions)
    {
    	$parameters = array(
    			'workout_type_name' => $workoutTypeName,
    			'parent_workout_type_id' => $parentWorkoutTypeId,
    			'o' => $this->responseFormat,
    	);
    	$parameters = array_merge($parameters, $additionalOptions);
    	return $this->fetchResponse('workouts/create_workout_type', 'GET', $parameters);
    }
    
    /**
     * This method deletes a Workout Record.
     * 
     * @throws Exception
     * @param int $workoutId Workout ID
     * @param int $workoutKey Workout Key
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function deleteWworkout($workoutId = null, $workoutKey = null)
    {
    	$parameters = array(
    			'workout_id' => $workoutId,
    			'workout_key' => $workoutKey,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('workouts/delete_workout', 'GET', $parameters);
    }
    
    /**
     * This method updates a User Workout
     *
     * @throws Exception
     * @param int $workoutId Workout ID
     * @param int $workoutKey Workout Key
     * @param array $additionalOptions An array of additional, optional arguments, with key/values following the format outlined at {@link http://api.mapmyfitness.com/3.1/workouts/edit_workout}
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function editWorkout($workoutId = null, $workoutKey = null, $additionalOptions)
    {
    	$parameters = array(
    			'workout_id' => $workoutId,
    			'workout_key' => $workoutKey,
    			'o' => $this->responseFormat,
    	);
    	$parameters = array_merge($parameters, $additionalOptions);
    	return $this->fetchResponse('workouts/edit_workout', 'GET', $parameters);
    }
    
    /**
     * This method returns Activity Types
     * 
     * @throws Exception
     * @param int $parentActivityTypeId Parent Activity Type ID
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getActivityTypes($parentActivityTypeId = null)
    {
	    $parameters = array(
	    		'parent_activity_type_id' => $parentActivityTypeId,
	    		'o' => $this->responseFormat,
	    );
	    return $this->fetchResponse('workouts/get_activity_types', 'GET', $parameters);
    }
    
    /**
     * This returns all the METS Compendium Records
     * 
     * @throws Exception
     * @param string $heading METS Compendium Heading
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getCompendiumCompcodes($heading)
    {
    	$parameters = array(
    			'heading' => $heading,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('workouts/get_compendium_compcodes', 'GET', $parameters);
    }
    
    /**
     * This method returns METS Compendium Headers.
     * 
     * @throws Exception
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function get_compendium_headings()
    {
    	return $this->fetchResponse('workouts/get_compendium_headings', 'GET', array('o' => $this->responseFormat));
    }
    
    /**
     * This method returns all Parent Workout Types
     * 
     * @throws Exception
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getParentWorkoutTypes()
    {
    	return $this->fetchResponse('workouts/get_parent_workout_types', 'GET', array('o' => $this->responseFormat));
    }
    
    /**
     * This method writes TCX Data to the Session
     * 
     * @throws Exception
     * @param string $sessionId Session ID
     * @param int $activityIndex Activity Index if Multi-Activity (ie, 0 = 1st Activity) 
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getTcxStats($sessionId, $activityIndex = null)
    {
    	$parameters = array(
    			'session_id' => $sessionId,
    			'activity_index' => $activityIndex,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('workouts/get_tcx_stats', 'GET', $parameters);
    }
    
    /**
     * This method allows you to search for workouts by user and date
     * 
     * @throws Exception
     * @param int $userId User ID
     * @param int $userKey User Key
     * @param array $additionalOptions An array of additional, optional arguments, with key/values following the format outlined at {@link http://api.mapmyfitness.com/3.1/workouts/get_user_workout_stats}
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getUserWorkoutStats($userId = null, $userKey = null, $additionalOptions)
    {
    	$parameters = array(
    			'user_id' => $userId,
    			'user_key' => $userKey,
    			'o' => $this->responseFormat,
    	);
    	$parameters = array_merge($parameters, $additionalOptions);
    	return $this->fetchResponse('workouts/get_user_workout_stats', 'GET', $parameters);
    }
    
    /**
     * This method returns a Workout Record
     * 
     * @throws Exception
     * @param int $userId User ID
     * @param int $userKey User Key
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getWorkout($userId = null, $userKey = null)
    {
    	$parameters = array(
    			'user_id' => $userId,
    			'user_key' => $userKey,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('workouts/get_workout', 'GET', $parameters);
    }
    
    /**
     * This method returns a Workout Record including Children
     * 
     * @throws Exception
     * @param int $userId User ID
     * @param int $userKey User Key
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>y
     */
    public function getWorkoutFull($userId = null, $userKey = null)
    {
    	$parameters = array(
    			'user_id' => $$userId,
    			'user_key' => $userKey,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('workouts/get_workout_full', 'GET', $parameters);
    }
    
    /**
     * This method returns Workout Lap Data
     * 
     * @throws Exception
     * @param int $userId User ID
     * @param int $userKey User Key
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getWorkoutLaps($userId = null, $userKey = null)
    {
    	$parameters = array(
    			'user_id' => $userId,
    			'user_key' => $userKey,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('workouts/get_workout_laps', 'GET', $parameters);
    }
    
    /**
     * This method returns Workout Types for a User
     * 
     * @throws Exception
     * @param int $userId User ID
     * @param int $parentWorkoutTypeId Parent Workout Type ID 
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getWorkoutTypes($userId, $parentWorkoutTypeId)
    {
    	$parameters = array(
    			'user_id' => $userId,
    			'parent_workout_type_id' => $parentWorkoutTypeId,
    			'o' => $this->responseFormat,
    	);
    	return $this->fetchResponse('workouts/get_workout_types', 'GET', $parameters);
    }
    
    /**
     * This method allows you to search for workouts by user
     * 
     * @throws Exception
     * @param int $userId User ID
     * @param int $userKey User Key
     * @param array $additionalOptions An array of additional, optional arguments, with key/values following the format outlined at {@link http://api.mapmyfitness.com/3.1/workouts/get_workouts}
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function getWorkouts($userId = null, $userKey = null, $additionalOptions)
    {
    	$parameters = array(
    			'user_id' => $userId,
    			'user_key' => $userKey,
    			'o' => $this->responseFormat,
    	);
    	$parameters = array_merge($parameters, $additionalOptions);
    	return $this->fetchResponse('workouts/get_workouts', 'GET', $parameters);
    }
    
    /**
     * This method Imports TCX Data as a Workout
     * 
     * @throws Exception
     * @param string $u
     * @param string $p
     * @param xml $tcx
     * @param array $additionalOptions An array of additional, optional arguments, with key/values following the format outlined at {@link http://api.mapmyfitness.com/3.1/workouts/import_tcx}
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function importTcx($u = null, $p = null, $tcx = null, $additionalOptions)
    {
    	$parameters = array(
    			'u' => $u,
    			'p' => $p,
    			'tcx' => $tcx,
    			'o' => $this->responseFormat,
    	);
    	$parameters = array_merge($parameters, $additionalOptions);
    	return $this->fetchResponse('workouts/import_tcx', 'GET', $parameters);
    }
    
    /**
     * This method creates a Workout Data Track for a Workout. Only Supports Single Lap
     * 
     * @throws Exception
     * @param string $workoutTrackName
     * @param int $workoutId
     * @param int $workoutKey
     * @param array $additionalOptions An array of additional, optional arguments, with key/values following the format outlined at {@link http://api.mapmyfitness.com/3.1/workouts/import_tcx}
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function saveWorkoutDataTrack($workoutTrackName, $workoutId = null, $workoutKey = null, $additionalOptions)
    {
    	$parameters = array(
    			'workout_track_name' => $workoutTrackName,
    			'workout_id' => $workoutId,
    			'workout_key' => $workoutKey,
    			'o' => $this->responseFormat,
    	);
    	$parameters = array_merge($parameters, $additionalOptions);
    	return $this->fetchResponse('workouts/save_workout_data_track', 'GET', $parameters);
    }
    
    /**
     * This method allows you to search for workouts by numerous criteria 
     * 
     * @throws Exception
     * @param string $keyword Searches the workout title and notes
     * @param array $additionalOptions An array of additional, optional arguments, with key/values following the format outlined at {@link http://api.mapmyfitness.com/3.1/workouts/import_tcx}
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function searchWorkouts($keyword = null, $additionalOptions)
    {
    	$parameters = array(
    			'keyword' => $keyword,
    			'o' => $this->responseFormat,
    	);
    	$parameters = array_merge($parameters, $additionalOptions);
    	return $this->fetchResponse('workouts/search_workouts', 'GET', $parameters);
    }
    
    /**
     * This method returns a custom list of Workout Types
     * 
     * @throws Exception
     * @param int $parentWorkoutTypeId Parent Workout Type Id
     * @param array $additionalOptions An array of additional, optional arguments, with key/values following the format outlined at {@link http://api.mapmyfitness.com/3.1/workouts/suggest_workout_types}
     * @return Ambigous <\MapMyFitness\mixed, unknown, SimpleXMLElement, mixed>
     */
    public function suggestWorkoutTypes($parentWorkoutTypeId = null,  $additionalOptions)
    {
    	$parameters = array(
    			'parent_workout_type_id' => $parentWorkoutTypeId,
    			'o' => $this->responseFormat,
    	);
    	$parameters = array_merge($parameters, $additionalOptions);
    	return $this->fetchResponse('workouts/suggest_workout_types', 'GET', $parameters);
    }
    
	/**
     * Make custom call to any API endpoint
     *
     * @param string $url Endpoint url after '.../1/'
     * @param array $parameters Request parameters
     * @param string $method (OAUTH_HTTP_METHOD_GET, OAUTH_HTTP_METHOD_POST, OAUTH_HTTP_METHOD_PUT, OAUTH_HTTP_METHOD_DELETE)
     * @param array $userHeaders Additional custom headers
     * @return Response
     */
    public function customCall($url, $parameters, $method, $userHeaders = array())
    {
        $response = $this->service->request($url, $method, $parameters, $userHeaders);
        return $this->parseResponse($response);
    }

    /**
     * @return mixed SimpleXMLElement, the value encoded in json as an object or PHP Array
     */
    private function parseResponse($response)
    {
    	switch($this->responseFormat) {
    		case 'xml':
    			return simplexml_load_string($response);
    		break;
    		case 'json':
    			return json_decode($response);
    		break;
    		case 'php':
    			return unserialize($response);
    		break;
    		default:
    			return $response;
    		break;
    	}
    }
}