<?php

namespace Filament\Resources\Pages\EditRecord\Concerns;

use Exception;
use Filament\Resources\Pages\Concerns\HasActiveFormLocaleSwitcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

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
            if ($translatableDataFromSession) {
                $data[$attribute] = $translatableDataFromSession[$attribute];
            } else {
                $data[$attribute] = $this->record->getTranslation($attribute, $this->activeFormLocale);
            }
        }
        // Nested translatable attributes
        // if ($translatableDataFromSession && method_exists(static::getResource(), 'getNestedTranslatableAttributes')) {
        //     foreach (static::getResource()::getNestedTranslatableAttributes() as $nestedTranslatableAttribute) {
        //         $translatedData = $this->pluckAndPreserveStructure($translatableDataFromSession, $nestedTranslatableAttribute);
        //         // $translatableAttributesData = array_merge($translatableAttributesData, $this->pluckAndPreserveStructure($data, $nestedTranslatableAttribute));
        //         $data = array_replace_recursive($data, $translatedData);
        //         // dd($translatedData, $data);
        //     }
        // }

        $data = $this->mutateFormDataBeforeFill($data);
        // dd($this->form->getComponents(withHidden: true));
        // $component = $this->getComponentByLabel('Chapters & Lessons.Chapters');
        // $component?->mutateRelationshipDataBeforeFillUsing(function ($data) {
        //     $data['name'] = $this->getAttributeTranslatedValue($data, 'record');

        //     return $data;
        // });
        // dd($component);

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
        $resource = static::getResource();
        $translatableAttributesData = Arr::only($data, $resource::getTranslatableAttributes());

        // Nested translatable attributes
        // if (method_exists($resource, 'getNestedTranslatableAttributes')) {
        //     foreach ($resource::getNestedTranslatableAttributes() as $nestedTranslatableAttribute) {
        //         $translatableAttributesData = array_merge($translatableAttributesData, $this->pluckAndPreserveStructure($data, $nestedTranslatableAttribute));
        //     }
        // }

        // dd(session($this->getTranslatableFormDataSessionKey()));

        session()->put($this->getTranslatableFormDataSessionKey($this->activeFormLocale), $translatableAttributesData);
    }

    protected function getAttributeTranslatedValue(array $defaultData, string $attribute, $locale = null)
    {
        $locale = $locale ?: $this->activeFormLocale;

        $translatedData = session($this->getTranslatableFormDataSessionKey($locale));
        
        if (!$translatedData) {
            return $defaultData;
        }
        
        $defaultData = collect($defaultData);
        dd($defaultData->pluck($attribute));
    }

    /**
     * Pluck data from an array but preserve the structure
     *
     * @param array $array
     * @param string $key
     * @return mixed
     */
    function pluckAndPreserveStructure(array $array, string $key): mixed
    {
        $result = [];
        [$firstKey, $remainingKeys] = explode('.*.', $key, 2) + [null, null];

        if (is_null($remainingKeys)) {
            // If the key does not contain `.*`, simply return the value at the key.
            return Arr::get($array, $key, null);
        } else {
            // If the key contains `.*`, pluck the sub-arrays at that key and recurse.
            foreach (Arr::get($array, $firstKey, []) as $index => $subArray) {
                $value = $this->pluckAndPreserveStructure($subArray, $remainingKeys);
                Arr::set($result, "{$firstKey}.{$index}.{$remainingKeys}", $value);
            }
        }

        return $result;
    }

    /**
     * TODO: Doc + fixme + warning of use (this is used to translate relationships, but we will not use anymore because we move the chapters section to a different location)
     * ! and sometimes it enters an infinit loop
     *
     * @param array|string $label
     * @param [type] $components
     * @return void
     */
    // protected function getComponentByLabel(array|string $label, $components = null)
    // {
    //     // TODO: replace label by name
        
    //     $labels = is_array($label) ? $label : explode('.', $label);
    //     $result = null;

    //     $components = $components ?: $this->form->getComponents(withHidden: true);

    //     $nextComponents = [];
    //     foreach ($components as $component) {
    //         try {
    //             if ($component->getLabel() == $labels[0]) {
    //                 if (count($labels) > 1) {
    //                     $nextComponents = [$component];
    //                     array_shift($labels);

    //                     break;
    //                 } else {
    //                     $result = $component;

    //                     break;
    //                 }
    //             }

    //             $nextComponents = array_merge($nextComponents, $component->getChildComponents());
    //         } catch (Throwable $th) {
    //             // pass
    //             // An error is thrown for some components because they are not initiated yet
    //         }
    //     }

    //     $result = $result ?: $this->getComponentByLabel($labels, $nextComponents);

    //     return $result;
    // }
}
