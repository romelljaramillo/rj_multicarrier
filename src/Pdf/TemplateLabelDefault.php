<?php
/**
 * Default label template implementation.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Pdf;

class TemplateLabelDefault extends TemplateLabel
{
    /**
     * Configuration disables customer account access for generated labels.
     */
    public $available_in_your_account = false;

    public function getContent(): void
    {
        parent::getContent();
    }

    public function getFilename(): string
    {
        return parent::getFilename();
    }
}
