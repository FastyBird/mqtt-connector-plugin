<?php declare(strict_types = 1);

use FastyBird\FbMqttConnector\Entities;

return [
	'attr-' . Entities\Messages\Attribute::NAME             => [
		'/fb/v1/device-name/$' . Entities\Messages\Attribute::NAME,
		'Some content',
		[
			'device'                          => 'device-name',
			'parent'                          => null,
			'retained'                        => false,
			Entities\Messages\Attribute::NAME => 'Some content',
		],
	],
	'attr-' . Entities\Messages\Attribute::PROPERTIES       => [
		'/fb/v1/device-name/$' . Entities\Messages\Attribute::PROPERTIES,
		'prop1,prop2',
		[
			'device'                                => 'device-name',
			'parent'                                => null,
			'retained'                              => false,
			Entities\Messages\Attribute::PROPERTIES => ['prop1', 'prop2'],
		],
	],
	'attr-' . Entities\Messages\Attribute::CHANNELS         => [
		'/fb/v1/device-name/$' . Entities\Messages\Attribute::CHANNELS,
		'channel-one,channel-two',
		[
			'device'                              => 'device-name',
			'parent'                              => null,
			'retained'                            => false,
			Entities\Messages\Attribute::CHANNELS => ['channel-one', 'channel-two'],
		],
	],
	'attr-' . Entities\Messages\Attribute::CONTROL          => [
		'/fb/v1/device-name/$' . Entities\Messages\Attribute::CONTROL,
		'configure,reset',
		[
			'device'                             => 'device-name',
			'parent'                             => null,
			'retained'                           => false,
			Entities\Messages\Attribute::CONTROL => ['configure', 'reset'],
		],
	],
	'child-attr-' . Entities\Messages\Attribute::NAME       => [
		'/fb/v1/device-name/$child/child-name/$' . Entities\Messages\Attribute::NAME,
		'Some content',
		[
			'device'                          => 'child-name',
			'parent'                          => 'device-name',
			'retained'                        => false,
			Entities\Messages\Attribute::NAME => 'Some content',
		],
	],
	'child-attr-' . Entities\Messages\Attribute::PROPERTIES => [
		'/fb/v1/device-name/$child/child-name/$' . Entities\Messages\Attribute::PROPERTIES,
		'prop1,prop2',
		[
			'device'                                => 'child-name',
			'parent'                                => 'device-name',
			'retained'                              => false,
			Entities\Messages\Attribute::PROPERTIES => ['prop1', 'prop2'],
		],
	],
	'child-attr-' . Entities\Messages\Attribute::CHANNELS   => [
		'/fb/v1/device-name/$child/child-name/$' . Entities\Messages\Attribute::CHANNELS,
		'channel-one,channel-two',
		[
			'device'                              => 'child-name',
			'parent'                              => 'device-name',
			'retained'                            => false,
			Entities\Messages\Attribute::CHANNELS => ['channel-one', 'channel-two'],
		],
	],
	'child-attr-' . Entities\Messages\Attribute::CONTROL    => [
		'/fb/v1/device-name/$child/child-name/$' . Entities\Messages\Attribute::CONTROL,
		'configure,reset',
		[
			'device'                             => 'child-name',
			'parent'                             => 'device-name',
			'retained'                           => false,
			Entities\Messages\Attribute::CONTROL => ['configure', 'reset'],
		],
	],
];
