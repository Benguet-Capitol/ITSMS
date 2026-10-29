<?php

namespace App\Support;

use App\Http\Resources\CctvFootageRequestResource;
use App\Http\Resources\DesignRequestResource;
use App\Http\Resources\DocumentationRequestResource;
use App\Http\Resources\DtsUserRequestResource;
use App\Http\Resources\InspectionRequestResource;
use App\Http\Resources\IsChangeRequestResource;
use App\Http\Resources\NetworkAssistanceRequestResource;
use App\Http\Resources\OtherItServiceRequestResource;
use App\Models\CctvFootageRequest;
use App\Models\DesignRequest;
use App\Models\DocumentationRequest;
use App\Models\DtsUserRequest;
use App\Models\InspectionRequest;
use App\Models\IsChangeRequest;
use App\Models\NetworkAssistanceRequest;
use App\Models\OtherItServiceRequest;
use Illuminate\Database\Eloquent\Model;

/**
 * Registry of the request types that use the shared accept/assign
 * workflow (HasRequestWorkflow). The key is the {type} segment of
 * /api/requests/{type}/{id}/... and the morph alias stored in
 * request_personnel. Add a type here when its module is converted.
 */
class RequestTypes
{
    public const TYPES = [
        'design' => [
            'model' => DesignRequest::class,
            'resource' => DesignRequestResource::class,
            'permission' => 'requests.design',
            'label' => 'Design/Lay-out/Editing Request',
            'path' => '/requests/design',
        ],
        'documentation' => [
            'model' => DocumentationRequest::class,
            'resource' => DocumentationRequestResource::class,
            'permission' => 'requests.documentation',
            'label' => 'Documentation Request',
            'path' => '/requests/documentation',
        ],
        'network_assistance' => [
            'model' => NetworkAssistanceRequest::class,
            'resource' => NetworkAssistanceRequestResource::class,
            'permission' => 'requests.network_assistance',
            'label' => 'Network Assistance Request',
            'path' => '/requests/network-assistance',
        ],
        'dts_user' => [
            'model' => DtsUserRequest::class,
            'resource' => DtsUserRequestResource::class,
            'permission' => 'requests.dts_user',
            'label' => 'DTS User Request',
            'path' => '/requests/dts-user',
        ],
        'other_it_service' => [
            'model' => OtherItServiceRequest::class,
            'resource' => OtherItServiceRequestResource::class,
            'permission' => 'requests.other_it_services',
            'label' => 'Other IT Service Request',
            'path' => '/requests/other-it-services',
        ],
        'cctv_footage' => [
            'model' => CctvFootageRequest::class,
            'resource' => CctvFootageRequestResource::class,
            'permission' => 'requests.cctv_footage',
            'label' => 'CCTV Footage Request',
            'path' => '/requests/cctv-footage',
        ],
        'is_change' => [
            'model' => IsChangeRequest::class,
            'resource' => IsChangeRequestResource::class,
            'permission' => 'requests.is_change',
            'label' => 'IS Change Request',
            'path' => '/requests/is-change',
        ],
        'inspection' => [
            'model' => InspectionRequest::class,
            'resource' => InspectionRequestResource::class,
            'permission' => 'requests.inspection',
            'label' => 'Inspection Request',
            'path' => '/requests/inspection',
        ],
    ];

    /** @return array{model: class-string<Model>, resource: class-string, permission: string, label: string, path: string}|null */
    public static function get(string $type): ?array
    {
        return self::TYPES[$type] ?? null;
    }

    /** @return array<string, class-string<Model>> morph alias => model */
    public static function morphMap(): array
    {
        return array_map(fn (array $config) => $config['model'], self::TYPES);
    }

    /** Registry entry for a model instance (looked up by its morph alias). */
    public static function forModel(Model $model): array
    {
        return self::TYPES[$model->getMorphClass()];
    }
}
