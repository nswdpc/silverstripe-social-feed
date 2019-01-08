<?php
namespace SilverstripeSocialFeed\Admin;
use SilverstripeSocialFeed\Provider\SocialFeedProvider;
use SilverstripeSocialFeed\Provider\InstagramProvider;
use SilverstripeSocialFeed\Provider\TwitterProvider;
use SilverstripeSocialFeed\Provider\FacebookProvider;
use Silverstripe\Core\Control\Director;
use Silverstripe\ORM\DataObject;
use SilverStripe\Admin\ModelAdmin;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

class SocialFeedAdmin extends ModelAdmin
{
    private static $managed_models = array(
        SocialFeedProvider::class,
    );

    private static $url_segment = 'social-feed';
    private static $menu_title = 'Social Feed';
    private static $menu_icon_class = 'font-icon-torsos-all';

    public function getEditForm($id = null, $fields = null) {
        $form = parent::getEditForm($id, $fields);

        if($this->modelClass == SocialFeedProvider::class) {
            /** @var GridField $gf */
            $gf = $form->Fields()->dataFieldByName($this->sanitiseClassName($this->modelClass));
            if($gf) {
                $gf->getConfig()->addComponent(new GridFieldOrderableRows('Sort'));
            }
        }

        return $form;
    }

}
