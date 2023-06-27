<?php

namespace Filament\Resources\Pages\EditRecord\Concerns;

use Filament\Resources\Pages\Concerns\HasActiveFormLocaleSwitcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

trait Translatable
{
    use HasActiveFormLocaleSwitcher;

    public $activeFormLocale = null;

    protected function fillForm(): void
    {
        $this->callHook('beforeFill');

        if ($this->activeFormLocale === null) {
            $this->setActiveFormLocale();
        }

        $data = $this->data ?? $this->record->attributesToArray();

        $translatableDataFromSession = session($this->getTranslatableFormDataSessionKey($this->activeFormLocale));
        foreach (static::getResource()::getTranslatableAttributes() as $attribute) {
            if ($translatableDataFromSession && isset($translatableDataFromSession[$attribute])) {
                $data[$attribute] = $translatableDataFromSession[$attribute];
            } else {
                $data[$attribute] = $this->record->getTranslation($attribute, $this->activeFormLocale);
            }
        }

        $data = $this->mutateFormDataBeforeFill($data);

        $this->form->fill($data);

        $this->callHook('afterFill');
    }

    protected function setActiveFormLocale(): void
    {
        $resource = static::getResource();

        $availableLocales = array_keys($this->record->getTranslations($resource::getTranslatableAttributes()[0]));
        $resourceLocales = $this->getTranslatableLocales();
        $defaultLocale = $resource::getDefaultTranslatableLocale();

        $this->activeLocale = $this->activeFormLocale = in_array($defaultLocale, $availableLocales) ? $defaultLocale : array_intersect($availableLocales, $resourceLocales)[0] ?? $defaultLocale;
        $this->record->setLocale($this->activeFormLocale);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
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
        $translatableAttributesData = Arr::only($data, $this->record->getTranslatableAttributes());

        session()->put($this->getTranslatableFormDataSessionKey($this->activeFormLocale), $translatableAttributesData);
    }
}
