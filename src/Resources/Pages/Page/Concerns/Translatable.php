<?php

namespace Filament\Resources\Pages\Page\Concerns;

use Filament\Resources\Pages\Concerns\HasActiveFormLocaleSwitcher;
use Filament\Resources\Pages\Concerns\HasActiveLocaleSwitcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

trait Translatable
{
    // use HasActiveLocaleSwitcher;
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
        foreach ($this->getTranslatableAttributes() as $attribute) {
            if ($translatableDataFromSession) {
                $data[$attribute] = $translatableDataFromSession[$attribute];
            } else {
                $data[$attribute] = method_exists($this, 'getTranslations') ? $this->getTranslations($attribute) : $this->record->getTranslation($attribute, $this->activeFormLocale);
            }
        }

        $data = method_exists($this, 'mutateFormDataBeforeFill') ? $this->mutateFormDataBeforeFill($data) : $data;

        $this->form->fill($data);

        $this->callHook('afterFill');
    }

    protected function setActiveFormLocale(): void
    {
        $resource = static::getResource();

        $availableLocales = array_keys($this->record->getTranslations($this->getTranslatableAttributes()[0]));
        $resourceLocales = $this->getTranslatableLocales();
        $defaultLocale = $resource::getDefaultTranslatableLocale();

        $this->activeLocale = $this->activeFormLocale = in_array($defaultLocale, $availableLocales) ? $defaultLocale : array_intersect($availableLocales, $resourceLocales)[0] ?? $defaultLocale;
        $this->record->setLocale($this->activeFormLocale);
    }

    // protected function handleRecordUpdate(Model $record, array $data): Model
    // {
    //     $record->fill(Arr::except($data, $this->getTranslatableAttributes()));

    //     $this->saveTranslatableFormDataInSession($this->data);
    //     $translatableDataFromSession = session($this->getTranslatableFormDataSessionKey());
    //     foreach ($translatableDataFromSession as $locale => $data) {
    //         foreach (Arr::only($data, $this->getTranslatableAttributes()) as $key => $value) {
    //             $record->setTranslation($key, $locale, $value);
    //         }
    //     }

    //     $record->save();

    //     // Deleting current form session data
    //     session()->forget($this->getTranslatableFormDataSessionKey());

    //     return $record;
    // }

    public function updatedActiveFormLocale(): void
    {
        $this->fillForm();
    }

    public function updatingActiveFormLocale(): void
    {
        $this->data = $this->form->getState(); // New line

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
        $translatableAttributesData = $this->getTranslatableAttributesData($data);

        if ($this->activeFormLocale == null) {
            $this->activeFormLocale = static::getResource()::getDefaultTranslatableLocale();
        }

        session()->put($this->getTranslatableFormDataSessionKey($this->activeFormLocale), $translatableAttributesData);
    }

    /**
     * Get translatable attributes
     *
     * @return array
     */
    public function getTranslatableAttributes(): array
    {
        // return $this->record->getTranslatableAttributes();
        return static::getResource()::getTranslatableAttributes();
    }

    /**
     * Get translatable attributesData
     *
     * @param array $data
     * @return array
     */
    public function getTranslatableAttributesData($data): array
    {
        return Arr::only($data, $this->getTranslatableAttributes());
    }

    /**
     * Forget translation session data
     *
     * @return void
     */
    public function forgetTranslationSession(): void
    {
        session()->forget($this->getTranslatableFormDataSessionKey());
    }
}
