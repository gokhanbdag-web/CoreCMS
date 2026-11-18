<?php
declare(strict_types=1);

namespace CoreCMS\Core;

final class FormFieldEditor
{
    public static function fromPost(mixed $submitted): array
    {
        if (!is_array($submitted)) {
            throw new \RuntimeException('Form fields must be submitted as structured fields.');
        }
        $fields = [];
        foreach ($submitted as $field) {
            if (!is_array($field)) {
                throw new \RuntimeException('Invalid form field definition.');
            }
            $optionsText = $field['options_text'] ?? '';
            if (!is_string($optionsText)) {
                throw new \RuntimeException('Invalid dropdown options.');
            }
            $options = [];
            foreach (preg_split('/\r\n|\r|\n/', $optionsText) ?: [] as $option) {
                $option = trim($option);
                if ($option !== '') $options[] = $option;
            }
            $fields[] = [
                'key' => (string) ($field['key'] ?? ''),
                'type' => (string) ($field['type'] ?? 'text'),
                'label' => (string) ($field['label'] ?? ''),
                'required' => isset($field['required']) && in_array($field['required'], ['1', 1, true, 'on'], true),
                'options' => $options,
            ];
        }
        return $fields;
    }
}
