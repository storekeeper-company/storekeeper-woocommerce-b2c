<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages;

use StoreKeeper\WooCommerce\B2C\Helpers\HtmlEscape;

trait FormElementTrait
{
    final protected function renderFormStart(string $method = 'get', ?string $action = null): void
    {
        $action = esc_url($action ?? add_query_arg([]));
        $method = esc_attr($method);

        echo wp_kses("<form action='$action' method='$method' class='storekeeper-form'>", HtmlEscape::ALLOWED_FORM);
    }

    final protected function renderFormEnd(): void
    {
        echo '</form>';
    }

    final protected function renderRequestHiddenInputs(): void
    {
        $html = '';

        foreach ($_REQUEST as $name => $value) {
            $value = esc_attr($value);
            $html .= "<input type='hidden' name='$name' value='$value' />";
        }

        echo wp_kses($html, HtmlEscape::ALLOWED_INPUT);
    }

    final protected function renderFormHeader(string $title): void
    {
        $title = esc_html($title);

        echo wp_kses(<<<HTML
<div class="storekeeper-form-group">
    <h2 class="storekeeper-form-header">$title</h2>
</div>
HTML, HtmlEscape::ALLOWED_COMMON);
    }

    final protected function renderFormNote(string $note, string $class = ''): void
    {
        $note = esc_html($note);
        $class = esc_attr($class);

        echo wp_kses(<<<HTML
<div class="storekeeper-form-group">
    <p class="storekeeper-form-note $class">$note</p>
</div>
HTML, HtmlEscape::ALLOWED_COMMON);
    }

    final protected function renderFormGroup(string $label, string $input): void
    {
        $label = esc_html($label);

        echo wp_kses(<<<HTML
<div class="storekeeper-form-group">
    <label class="storekeeper-form-label">$label</label>
    <div class="storekeeper-form-input">$input</div>
</div>
HTML, HtmlEscape::ALLOWED_ALL_KNOWN_INPUT);
    }

    final protected function renderFormActionGroup(string $actions): void
    {
        echo wp_kses("<div class='storekeeper-form-action-group'>$actions</div>", HtmlEscape::ALLOWED_ALL_KNOWN_INPUT);
    }

    final protected function renderFormHiddenInput(string $name, string $value = ''): void
    {
        $name = esc_attr($name);
        $value = esc_attr($value);

        echo wp_kses("<input type='hidden' name='$name' value='$value'/>", HtmlEscape::ALLOWED_INPUT);
    }

    final protected function getFormInput(
        string $name,
        string $placeholder,
        string $value = '',
        string $class = '',
        string $type = 'text'
    ): string {
        $name = esc_attr($name);
        $placeholder = esc_attr($placeholder);
        $value = esc_attr($value);
        $class = esc_attr($class);
        $type = esc_attr($type);

        return "<input type='$type' class='$class' name='$name' value='$value' placeholder='$placeholder' />";
    }

    final protected function getFormCheckbox(
        string $name,
        bool $value = false,
        string $class = '',
        bool $isDisabled = false
    ): string {
        $name = esc_attr($name);
        $checked = esc_attr($value) ? 'checked' : '';
        $disabled = esc_attr($isDisabled) ? 'disabled="disabled"' : '';
        $class = esc_attr($class);

        return "<input type='checkbox' class='$class' name='$name' $checked $disabled/>";
    }

    final protected function getFormButton(
        string $label,
        string $class = 'button',
        string $name = '',
        string $value = '',
        string $type = 'submit'
    ): string {
        $label = esc_attr($label);
        $class = esc_attr($class);
        $name = esc_attr($name);
        $value = esc_attr($value);
        $type = esc_attr($type);

        return "<button type='$type' class='$class' name='$name' value='$value'>$label</button>";
    }

    final protected function getFormLink(
        string $href,
        string $label,
        string $class = '',
        string $target = '_self'
    ): string {
        $href = esc_url($href);
        $label = esc_html($label);

        if ('_blank' === $target) {
            $label .= ' <i class="wf-fa wf-fa-external-link"></i>';
        }
        $target = esc_attr($target);

        return "<a href='$href' class='$class' target='$target'>$label</a>";
    }

    final protected function getFormSelect(string $name, array $options, string $value = '', string $class = ''): string
    {
        $name = esc_attr($name);
        $class = esc_attr($class);

        $optionHtml = '';
        foreach ($options as $optionValue => $optionLabel) {
            $selected = $optionValue === $value ? 'selected' : '';
            $optionValue = esc_attr($optionValue);
            $optionLabel = esc_html($optionLabel);
            $optionHtml .= "<option value='$optionValue' $selected>$optionLabel</option>";
        }

        return "<select name='$name' class='$class'>$optionHtml</select>";
    }
}
