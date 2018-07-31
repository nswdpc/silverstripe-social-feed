<?php
namespace SilverstripeSocialFeed\Admin;
use SilverstripeSocialFeed\Provider\InstagramProvider;
use SilverstripeSocialFeed\Provider\TwitterProvider;
use SilverstripeSocialFeed\Provider\FacebookProvider;
use Silverstripe\Core\Control\Director;
use Silverstripe\ORM\DataObject;
use SilverStripe\Admin\ModelAdmin;

class SocialFeedAdmin extends ModelAdmin
{
	private static $managed_models = array(
		FacebookProvider::class,
		TwitterProvider::class,
		InstagramProvider::class
	);

	private static $url_segment = 'social-feed';

	private static $menu_title = 'Social Feed';

	private static $menu_icon_class = 'font-icon-torsos-all';

	public function init()
	{
		parent::init();

		// get the currently managed model
		$model = $this->modelClass;

		// Instagram OAuth flow in action
		if($model == InstagramProvider::class && isset($_GET['provider_id']) && is_numeric($_GET['provider_id']) && isset($_GET['code'])) {
			// Find provider
			$instagramProvider = DataObject::get_by_id(InstagramProvider::class, $_GET['provider_id']);

			// Fetch access token using code
			$accessToken = $instagramProvider->fetchAccessToken($_GET['code']);

			// Set and save access token
			$instagramProvider->AccessToken = $accessToken->getToken();
			$instagramProvider->write();

			// Send user back to edit page
			// TODO: show user a notification?
			$sanitised = $this->sanitiseClassName($model);
			header('Location: ' . Director::absoluteBaseURL() . 'admin/social-feed/' . $sanitised . '/EditForm/field/' . $sanitised . '/item/' . $_GET['provider_id'] . '/edit');
			exit;
		}
	}
}
