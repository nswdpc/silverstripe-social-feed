<?php
namespace SilverstripeSocialFeed\Jobs;
use SilverstripeSocialFeed\Provider\SocialFeedProvider;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Exception;

class SocialFeedClearCacheJob extends AbstractQueuedJob {

    /**
     * Get the name of the job to show in the QueuedJobsAdmin.
     */
    public function getTitle() {
        return 'Social Feed - Clear Cache';
    }

    /**
     * Remove cache for each provider, enabled or not
     */
    public function process() {
        $list = SocialFeedProvider::get();
		$this->addMessage('Caches:' . $list->count(), 'INFO');
        foreach($list as $provider) {
            try {
                $provider->clearFeedCache();
                $this->addMessage('Cleared cache for ' . $provider->Label, 'INFO');
            } catch (Exception $e) {
                $this->addMessage($e->getMessage(), 'ERR');
            }
        }
        $this->isComplete = true;
    }
}
