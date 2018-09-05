<?php
namespace SilverstripeSocialFeed\Provider;

interface ProviderInterface
{
	public function getType();
	public function getFeed();
	public function getPostContent($post, $strip_html = true);
	public function getRawPostContent($post);
	public function getPostCreated($post);
	public function getPostUrl($post);
	public function getUserName($post);
	public function getImage($post);
	public function getImageLowRes($post);
	public function getImageThumb($post);
	public function getPostType($post);
}
