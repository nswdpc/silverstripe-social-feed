<?php
namespace SilverstripeSocialFeed\Extensions;
use SilverstripeSocialFeed\Provider\SocialFeedProvider;
use Silverstripe\Control\Director;
use Silverstripe\Core\Extension;
use Silverstripe\ORM\ArrayList;
use Silverstripe\View\ArrayData;

class SocialFeedControllerExtension extends Extension
{

	public function SocialFeed() {
		$providers = SocialFeedProvider::get()->filter('Enabled', 1);
		$result = new ArrayList();
		if($providers->count() == 0) {
			return false;
		}

		foreach ($providers as $provider) {
			if ($feed = $provider->getFeed()) {
				foreach ($feed->toArray() as $post) {
					$result->push( new ArrayData( $post ) );
				}
			}
		}
		$result = $result->sort('Created DESC');
		return $result;
	}
}
