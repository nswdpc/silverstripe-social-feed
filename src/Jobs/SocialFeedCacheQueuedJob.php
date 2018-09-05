<?php
namespace SilverstripeSocialFeed\Jobs;
use SilverstripeSocialFeed\Provider\SocialFeedProvider;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use DateTime;

class SocialFeedCacheQueuedJob extends AbstractQueuedJob {

	use Configurable;

	private static $time_offset = 900;

	/**
	 * Create a job based on the configured time_offset
	 */
	public function createJob(SocialFeedProvider $provider) {
		$time_offset = Config::inst()->get(SocialFeedCacheQueuedJob::class, 'time_offset');
		if($time_offset <= 0) {
			$time_offset = 900;
		}
		$run_date = new DateTime();
		$run_date->modify("+" . $time_offset . ' seconds');
		singleton( QueuedJobService::class )
			->queueJob(
				new SocialFeedCacheQueuedJob($provider),
				$run_date->format('Y-m-d H:i:s')
			);
	}

	public function __construct($provider = null) {
		if ($provider && $provider instanceof SocialFeedProvider) {
			$this->setObject($provider);
			$this->totalSteps = 1;
		}
	}

	/**
	 * Get the name of the job to show in the QueuedJobsAdmin.
	 */
	public function getTitle() {
		$provider = $this->getObject();
		return _t(
			'SocialFeed.SCHEDULEJOBTITLE',
			sprintf(
				'Social Feed - Update cache for #%d "%s"',
				$provider->ID,
				$provider->Label
			)
		);
	}

	/**
	 * Gets the providers feed and stores it in the
	 * providers cache.
	 */
	public function process() {
		$provider = $this->getObject();
		if ($provider && $provider instanceof SocialFeedProvider)
		{
			$feed = $provider->getFeedUncached();
			$provider->setFeedCache($feed);
		}
		$this->currentStep = 1;
		$this->isComplete = true;
	}

	/**
	 * Called when the job is determined to be 'complete'
	 */
	public function afterComplete() {
		$provider = $this->getObject();
		if ($provider && $provider instanceof SocialFeedProvider)
		{
			// Create next job
			$this->createJob($provider);
		}
	}
}
