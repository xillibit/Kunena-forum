<?php
/**
 * Kunena Component
 *
 * @package         Kunena.Template.Aurelia
 * @subpackage      BBCode
 *
 * @copyright       Copyright (C) 2008 - 2020 Kunena Team. All rights reserved.
 * @license         https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link            https://www.kunena.org
 **/
defined('_JEXEC') or die();

$attachment = $this->attachment;

echo $attachment->isImage() ? $this->render('image') : $this->render('general');
