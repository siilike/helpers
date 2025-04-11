<?php
namespace Siilike\Helpers\Illuminate;

class UTF8Validator implements \Illuminate\Contracts\Validation\Rule
{
	public function passes($attribute, $value)
	{
		return mb_check_encoding($value, 'UTF-8');
	}

	public function message()
	{
		return ':attribute must be valid UTF-8.';
	}
}
