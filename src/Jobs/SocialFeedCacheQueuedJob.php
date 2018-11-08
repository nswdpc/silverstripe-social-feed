<?php
namespace SilverstripeSocialFeed\Jobs;
use SilverstripeSocialFeed\Provider\SocialFeedProvider;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use DateTime;
use Exception;

class SocialFeedCacheQueuedJob extends AbstractQueuedJob {

	use Configurable;

	private static $time_offset = 900;

	public function __construct($provider_type = '', $provider_id = null) {
		$this->provider_type = trim($provider_type);
		$this->provider_id = $provider_id;
	}

	/**
	 * Create a job based on the configured time_offset
	 */
	public function createJob($provider_type = '', $provider_id = null) {
		$time_offset = Config::inst()->get(SocialFeedCacheQueuedJob::class, 'time_offset');
		if($time_offset <= 0) {
			$time_offset = 900;
		}
		$run_date = new DateTime();
		$run_date->modify("+" . $time_offset . ' seconds');
		singleton( QueuedJobService::class )
			->queueJob(
				new SocialFeedCacheQueuedJob($provider_type, $provider_id),
				$run_date->format('Y-m-d H:i:s')
			);
	}

	/**
	 * Get the name of the job to show in the QueuedJobsAdmin.
	 */
	public function getTitle() {
		return _t(
			'SocialFeed.SCHEDULEJOBTITLE',
			sprintf(
				'Social Feed - Update feed cache for provider type=%s id=%s',
				$this->provider_type,
				$this->provider_id
			)
		);
	}

	/**
	 * Gets the providers feed and stores it in the
	 * providers cache.
	 */
	public function process() {
		if($this->provider_type || $this->provider_id) {
			$providers = SocialFeedProvider::get()->filter('Enabled', 1);
			$provider_type = trim($this->provider_type);
			if($provider_type) {
				$classname = "SilverstripeSocialFeed\\Provider\\" . $provider_type;
				$providers = $providers->filter('ClassName', $classname);
			}
			if($this->provider_id) {
				$providers = $providers->filter('ID', $this->provider_id);
			}
			$count = $providers->count();
			if($count == 0) {
				throw new Exception("No providers found for type={$provider_type} id={$this->provider_id}");
			}
			foreach($providers as $provider) {
				$feed = $provider->getFeedUncached();
				$provider->setFeedCache($feed);
			}
		}
		$this->isComplete = true;
	}

	/**
	 * Called when the job is determined to be 'complete'
	 */
	public function afterComplete() {
		$this->createJob($this->provider_type, $this->provider_id);
	}
}
