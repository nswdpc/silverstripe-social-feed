<?php
namespace SilverstripeSocialFeed\Extensions;
use SilverstripeSocialFeed\Provider\SocialFeedProvider;
use SilverstripeSocialFeed\Provider\Facebook;
use SilverstripeSocialFeed\Provider\Twitter;
use SilverstripeSocialFeed\Provider\Instagram;
use Silverstripe\Control\Director;
use Silverstripe\Core\Extension;
use Silverstripe\ORM\ArrayList;

class SocialFeedControllerExtension extends Extension
{
	public function onBeforeInit()
	{
		// Allow easy clearing of the cache in dev mode
		if (Director::isDev() && isset($_GET['socialfeedclearcache']) && $_GET['socialfeedclearcache'] == 1) {
			foreach (SocialFeedProvider::get() as $prov) {
				$prov->clearFeedCache();
			}
		}
	}

	public function SocialFeed()
	{
		$combinedData = $this->getProviderFeed(Instagram::get()->filter('Enabled', 1));
		$combinedData = $this->getProviderFeed(Facebook::get()->filter('Enabled', 1), $combinedData);
		$combinedData = $this->getProviderFeed(Twitter::get()->filter('Enabled', 1), $combinedData);

		$result = new ArrayList($combinedData);
		$result = $result->sort('Created', 'DESC');
		return $result;
	}

	private function getProviderFeed($providers, $data = array())
	{
		foreach ($providers as $prov) {
			if (is_subclass_of($prov, SocialFeedProvider::class)) {
				if ($feed = $prov->getFeed()) {
					foreach ($feed->toArray() as $post) {
						$data[] = $post;
					}
				}
			}
		}
		return $data;
	}
}
