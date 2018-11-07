<?php
namespace SilverstripeSocialFeed\Admin;
use SilverstripeSocialFeed\Provider\SocialFeedProvider;
use SilverstripeSocialFeed\Provider\InstagramProvider;
use SilverstripeSocialFeed\Provider\TwitterProvider;
use SilverstripeSocialFeed\Provider\FacebookProvider;
use Silverstripe\Core\Control\Director;
use Silverstripe\ORM\DataObject;
use SilverStripe\Admin\ModelAdmin;

class SocialFeedAdmin extends ModelAdmin
{
	private static $managed_models = array(
		SocialFeedProvider::class,
	);
	private static $url_segment = 'social-feed';
	private static $menu_title = 'Social Feed';
	private static $menu_icon_class = 'font-icon-torsos-all';

}
