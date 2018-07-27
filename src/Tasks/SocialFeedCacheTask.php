<?php
namespace SilverstripeSocialFeed\Tasks;
use SilverstripeSocialFeed\Provider\SocialFeedProvider;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;

class SocialFeedCacheTask extends BuildTask {
	protected $title       = 'Social Feed Pre-Load Task';
	protected $description = 'Calls getFeed on each SocialFeedProvider and caches it. This task exists so a cronjob can be setup to update social feeds without exposing an end user to slowdown.';

	/**
	 * Gets the feed for each provider and updates the cache with it.
	 */
	public function run($request) {
		if( $providers = SocialFeedProvider::get()->filter('Enabled', 1) ) {
			foreach ($providers as $provider) {
				try {
					$this->log("Getting feed for #{$provider->ID} ({$provider->sanitiseClassName()})");
					$feed = $provider->getFeedUncached();
					$provider->setFeedCache($feed);
					$this->log("\tUpdated feed cache for #{$provider->ID} ({$provider->sanitiseClassName()})");
					if($feed) {
						$this->log("\t\tGot feed for {$provider->sanitiseClassName()}");
					} else {
						$this->log("\t\tEmpty feed for {$provider->sanitiseClassName()}");
					}
				} catch (Exception $e) {
					$this->log($e->getMessage(), "error");
				}
			}
		} else {
			$this->log('\tNo SocialFeedProvider records exist to be updated.');
		}
	}

	public function log($message, $type = "info") {
		DB::alteration_message($message);
	}
}
