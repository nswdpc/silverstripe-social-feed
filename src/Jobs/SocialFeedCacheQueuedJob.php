<?php
namespace SilverstripeSocialFeed\Jobs;
use SilverstripeSocialFeed\Provider\SocialFeedProvider;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;

class SocialFeedCacheQueueJob extends AbstractQueuedJob {

	use Configurable;

	private static $time_offset = 900;

	/**
	 * Setup job that updates the feed cache 5 minutes before it expires so
	 * the end-user doesn't experience page-load time slowdown.
	 */
	public function createJob(SocialFeedProvider $provider) {
		$time_offset = $this->config->get(__CLASS__, 'time_offset');
		if($time_offset <= 0) {
			$time_offset = 900;
		}
		$run_date = new DateTime();
		$run_date->modify("+" . $time_offset + ' seconds');
		singleton( QueuedJobService::class )->queueJob(new SocialFeedCacheQueueJob($provider), $run_date->format('Y-m-d H:i:s'));
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
			'Social Feed - Update cache for "{label}" ({class})',
			'',
			array(
				'class' => $provider->sanitiseClassName(),
				'label' => $provider->Label
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
