<?php
namespace SilverstripeSocialFeed\Extensions;
use SilverstripeSocialFeed\Provider\SocialFeedProvider;
use Silverstripe\Control\Director;
use Silverstripe\Core\Extension;
use Silverstripe\ORM\ArrayList;
use Silverstripe\View\ArrayData;

class SocialFeedControllerExtension extends Extension
{

    /**
     * Returns a bundle of all enabled provider feed items, sorted by most recent first
     * @returns DataList
     */
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

    /**
     * Returns a sorted list of providers with their feeds
     * @param mixed $enabled whether to return Enabled / Not Enabled / All providers
     */
    public function GroupedSocialFeed($enabled = 1) {
        $grouped_feed = [];
        $feeds = SocialFeedProvider::get()->sort('Sort ASC');
        if($enabled != null) {
            $feeds = $feeds->filter('Enabled', ($enabled == 1 ? 1 : 0));
        }
        foreach($feeds as $provider) {
            $type = $provider->getType();
            $grouped_feed[ $type ] = [
                'Type' => $type,
                'CssType' => $provider->getCssType(),
                'Provider' => $provider,
                'Feed' => $provider->getFeed()
            ];
        }
        return ArrayList::create( $grouped_feed );
    }

}
