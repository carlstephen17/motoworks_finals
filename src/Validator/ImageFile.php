<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class ImageFile extends Constraint
{
    public string $message = 'Upload a valid JPEG, PNG, or WebP image.';
}
