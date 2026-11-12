<?php

namespace App\Validator;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class ImageFileValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if ($value === null || !$value instanceof UploadedFile || !$value->isValid()) {
            return;
        }

        $image = getimagesize($value->getPathname());
        if (!is_array($image) || !in_array($image[2] ?? null, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
