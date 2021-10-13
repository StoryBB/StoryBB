<?php

/**
 * Generic admin settings page handler.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Controller\Admin;

use StoryBB\App;
use StoryBB\Container;
use StoryBB\Phrase;
use StoryBB\Controller\Administrative;
use StoryBB\Controller\Admin\AdminNavigation;
use StoryBB\Controller\MaintenanceAccessible;
use StoryBB\Dependency\SiteSettings;
use StoryBB\Form\Element;
use StoryBB\Routing\RenderResponse;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

abstract class AbstractSettingsPageController extends AbstractAdminController implements MaintenanceAccessible
{
	use SiteSettings;

	const BASE_ROUTE = 'does/not/exist';

	public static function register_own_routes(RouteCollection $routes): void
	{
		$routes->add(static::BASE_ROUTE, (new Route('/' . static::BASE_ROUTE, ['_controller' => [static::class, 'display_settings']]))->setMethods(['GET']));
		$routes->add(static::BASE_ROUTE . '/save', (new Route('/' . static::BASE_ROUTE, ['_controller' => [static::class, 'save_settings']]))->setMethods(['POST']));
	}

	abstract public function get_title(): Phrase;

	abstract public function define_settings(): array;

	protected function load_form()
	{
		$settings = $this->define_settings();
		class_alias('StoryBB\\Form\\General\\EmptyForm', static::class . '\\SettingsForm');
		$form = App::make(static::class . '\\SettingsForm', App::container()->get('url')->generate(static::BASE_ROUTE . '/save'));

		$settings_to_load = [];

		foreach ($settings as $section_label => $section_settings)
		{
			$section = $form->add_section($section_label);
			foreach ($section_settings as $setting_name => $setting_details)
			{
				if (!is_array($setting_details))
				{
					continue;
				}

				$element = $setting_details[0];
				if (!is_subclass_of($element, Element\Inputtable::class))
				{
					continue;
				}

				$settings_to_load[] = $setting_name;

				$label = $setting_details['phrase'] ?? 'SiteSettings:' . $setting_name;
				$form_element = $section->add(new $element($setting_name))->label($label);

				if (isset($setting_details['required']) && method_exists($element, 'required'))
				{
					$form_element->required();
				}

				if (isset($setting_details['choices']) && method_exists($element, 'choices'))
				{
					$form_element->choices($setting_details['choices']);
				}
			}
		}

		$section = $form->add_section('');
		$section->add(new Element\Buttons('submit'))->choices(['save' => 'General:save']);

		$site_settings = $this->sitesettings();

		foreach ($settings_to_load as $setting)
		{
			$form->set_default_data($setting, $site_settings->$setting);
		}

		return $form;
	}

	public function display_settings()
	{
		$form = $this->load_form();
		return $this->render('admin/settings_page.twig', static::BASE_ROUTE, [
			'title' => $this->get_title(),
			'form' => $form->render(),
		]);
	}

	public function save_settings()
	{
		$form = $this->load_form();

		$result = $form->get_data();
		if (!$result)
		{
			return $this->render('admin/settings_page.twig', static::BASE_ROUTE, [
				'title' => $this->get_title(),
				'form' => $form->render(),
			]);
		}

		unset ($result['submit']);

		$this->sitesettings()->save($result);

		return new RedirectResponse(App::container()->get('url')->generate(static::BASE_ROUTE));
	}
}
