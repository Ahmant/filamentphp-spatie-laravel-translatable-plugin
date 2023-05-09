<?php

namespace Filament\Resources\Pages\CreateRecord\Concerns;

use Filament\Resources\Pages\Concerns\HasActiveFormLocaleSwitcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

trait Translatable
{
	use HasActiveFormLocaleSwitcher;

	public function mount(): void
	{
		static::authorizeResourceAccess();

		abort_unless(static::getResource()::canCreate(), 403);

		$this->setActiveFormLocale();

		$this->fillForm();
	}

	protected function fillForm(): void
	{
		$this->callHook('beforeFill');

		if ($this->activeFormLocale === null) {
			$this->setActiveFormLocale();
		}
		$data = $this->data;

		if ($data) {
			// Don't enter this function if "$data == null" (first form load), to load the default values of the fields
			// If this function is entered on the first form load, the default fields' values will be overridden by an empty value
			$translatableDataFromSession = session($this->getTranslatableFormDataSessionKey($this->activeFormLocale));
			foreach (static::getResource()::getTranslatableAttributes() as $attribute) {
				if ($translatableDataFromSession) {
					$data[$attribute] = $translatableDataFromSession[$attribute];
				} else {
					$data[$attribute] = null;
				}
			}
		}

		$data = method_exists($this, 'mutateFormDataBeforeFill') ? $this->mutateFormDataBeforeFill($data) : $data;

		$this->form->fill($data);

		$this->callHook('afterFill');
	}

	protected function setActiveFormLocale(): void
	{
		$this->activeLocale = $this->activeFormLocale = static::getResource()::getDefaultTranslatableLocale();
	}

	protected function handleRecordCreation(array $data): Model
	{
		$record = app(static::getModel());
		$record->fill(Arr::except($data, $record->getTranslatableAttributes()));

		$this->saveTranslatableFormDataInSession($this->data);
		$translatableDataFromSession = session($this->getTranslatableFormDataSessionKey());
		foreach ($translatableDataFromSession as $locale => $data) {
			foreach (Arr::only($data, $record->getTranslatableAttributes()) as $key => $value) {
				$record->setTranslation($key, $locale, $value);
			}
		}

		$record->save();

		// Deleting current form session data
		session()->forget($this->getTranslatableFormDataSessionKey());

		return $record;
	}

	public function updatedActiveFormLocale(): void
	{
		$this->fillForm();
	}

	public function updatingActiveFormLocale(): void
	{
		$this->saveTranslatableFormDataInSession($this->data);
	}

	protected function getActions(): array
	{
		return array_merge(
			[$this->getActiveFormLocaleSelectAction()],
			parent::getActions(),
		);
	}

	/**
	 * Get translatable form data session key
	 *
	 * @return string
	 */
	protected function getTranslatableFormDataSessionKey($locale = null): string
	{
		$sessionKey = 'form_translation.' . $this->id;

		if ($locale) {
			$sessionKey .= '.' . $locale;
		}

		return $sessionKey;
	}

	/**
	 * Save translatable form data in session
	 *
	 * @param array $data
	 * @return void
	 */
	protected function saveTranslatableFormDataInSession(array $data): void
	{
		$resource = static::getResource();
		$translatableAttributesData = Arr::only($data, $resource::getTranslatableAttributes());

		session()->put($this->getTranslatableFormDataSessionKey($this->activeFormLocale), $translatableAttributesData);
	}

	/**
	 * Called after record created (hook)
	 *
	 * @return void
	 */
	protected function afterCreate(): void
	{
		// Ensure that the form data are cleared (To correctly empty the form after "Create and create another")
		$this->data = null;
	}
}
