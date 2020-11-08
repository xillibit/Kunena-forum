<?php
/**
 * Kunena Plugin
 *
 * @package         Kunena.Plugins
 * @subpackage      AltaUserPoints
 *
 * @copyright       Copyright (C) 2008 - 2020 Kunena Team. All rights reserved.
 * @license         https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link            https://www.kunena.org
 **/

namespace Kunena\Forum\Plugin\Kunena\Altauserpoints;

defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Kunena\Forum\Libraries\Factory\KunenaFactory;
use Kunena\Forum\Libraries\Integration\KunenaAvatar;
use function defined;

/**
 * Class \Kunena\Forum\Libraries\Integration\AvatarAltaUserPoints
 *
 * @since   Kunena 6.0
 */
class AvatarAltaUserPoints extends KunenaAvatar
{
	/**
	 * @var     null
	 * @since   Kunena 6.0
	 */
	protected $params = null;

	/**
	 * \Kunena\Forum\Libraries\Integration\AvatarAltaUserPoints constructor.
	 *
	 * @param   object  $params  params
	 *
	 * @since   Kunena 6.0
	 */
	public function __construct($params)
	{
		$this->params = $params;
	}

	/**
	 * @return  mixed
	 *
	 * @since   Kunena 6.0
	 */
	public function getEditURL()
	{
		return Route::_('index.php?option=com_altauserpoints&view=account');
	}

	/**
	 * @param $user
	 * @param $sizex
	 * @param $sizey
	 *
	 * @return  string|void
	 * @since   Kunena 6.0
	 */
	public function _getURL($user, $sizex, $sizey)
	{
		trigger_error(__CLASS__ . '::' . __FUNCTION__ . '() not implemented');
	}

	/**
	 * @param           $user
	 * @param   string  $class  class
	 * @param   int     $sizex  sizex
	 * @param   int     $sizey  sizey
	 *
	 * @return  string
	 *
	 * @since   Kunena 6.0
	 *
	 * @throws  Exception
	 */
	public function getLink($user, $class = '', $sizex = 90, $sizey = 90): string
	{
		$user = KunenaFactory::getUser($user);
		$size = $this->getSize($sizex, $sizey);

		if ($size->y > 100)
		{
			$profile = AltaUserPointsHelper::getUserInfo('', $user->userid);

			$avatar = ($profile->avatar != '') ? _AUP_AVATAR_LIVE_PATH . $profile->avatar : JPATH_ROOT . '/components/com_altauserpoints/assets/images/avatars/generic_gravatar_grey.png';
			$width  = 100 * (float) $size->x / (float) $size->y;
			$avatar = '<img loading="lazy" src="' . $avatar . '" border="0" alt="" width="' . $width . '" height="100" />';
		}
		else
		{
			$profile = AltaUserPointsHelper::getUserInfo('', $user->userid);

			$avatar = ($profile->avatar != '') ? Uri::root() . '/components/com_altauserpoints/assets/images/avatars/' . $profile->avatar : Uri::root() . '/components/com_altauserpoints/assets/images/avatars/' . 'generic_gravatar_grey.png';

			$avatar = '<img loading="lazy" src="' . $avatar . '" border="0" alt="" width="' . $size->x . '" height="' . $size->y . '" />';
		}

		if (!$avatar)
		{
			$avatar = '<img border="0" width="100" height="100" alt="" src="' . Uri::root() . 'components/com_altauserpoints/assets/images/avatars/generic_gravatar_grey.png">';
		}

		return $avatar;
	}
}
