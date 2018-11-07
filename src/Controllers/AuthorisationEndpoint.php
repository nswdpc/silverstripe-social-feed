<?php
namespace SilverstripeSocialFeed\Controllers;
use SilverstripeSocialFeed\Provider\SocialFeedProvider;
use SilverStripe\Control\HttpRequest;
use SilverStripe\Control\Controller;
use Exception;

/**
 * Provides a controller that social feeds can use to authorize requests e.g access tokens
 */
class AuthorisationController extends Controller {

	public function index(HttpRequest $request) {
		try {
			$type = $request->getVar('type');
			$code = $request->getVar('code');
			$provider = $request->getVar('provider');

			$instance = SocialFeedProvider::get()->byId($provider);
			if(empty($instance->ID)) {
				throw new Exception("Provider not found");
			}
			$check_type = $instance->getType();
			if($check_type !== $type) {
				throw new Exception("Sorry, the type {$type} does not match the expected provider type.");
				exit;
			}
			$result = $instance->finaliseAuthorisation($request->getVars());
			if(!$result) {
				throw new Exception("Authorisation finalisation failed");
			}
		} catch (Exception $e) {
			header("HTTP/1.1 500 Server Error");
			print $e->getMessage();
			exit;
		}
	}
}
