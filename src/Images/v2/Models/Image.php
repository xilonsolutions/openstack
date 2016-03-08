<?php declare(strict_types=1);

namespace OpenStack\Images\v2\Models;

use function GuzzleHttp\Psr7\uri_for;
use OpenCloud\Common\Resource\AbstractResource;
use OpenCloud\Common\Resource\Creatable;
use OpenCloud\Common\Resource\Deletable;
use OpenCloud\Common\Resource\Listable;
use OpenCloud\Common\Resource\Retrievable;
use OpenCloud\Common\Transport\Utils;
use OpenStack\Images\v2\JsonPatch;
use Psr\Http\Message\StreamInterface;

/**
 * @property \OpenStack\Images\v2\Api $api
 */
class Image extends AbstractResource implements Creatable, Listable, Retrievable, Deletable
{
    /** @var string */
    public $status;

    /** @var string */
    public $name;

    /** @var array */
    public $tags;

    /** @var string */
    public $containerFormat;

    /** @var \DateTimeImmutable */
    public $createdAt;

    /** @var string */
    public $diskFormat;

    /** @var \DateTimeImmutable */
    public $updatedAt;

    /** @var string */
    public $visibility;

    /** @var int */
    public $minDisk;

    /** @var bool */
    public $protected;

    /** @var string */
    public $id;

    /** @var \GuzzleHttp\Psr7\Uri */
    public $fileUri;

    /** @var string */
    public $checksum;

    /** @var string */
    public $ownerId;

    /** @var int */
    public $size;

    /** @var int */
    public $minRam;

    /** @var \GuzzleHttp\Psr7\Uri */
    public $schemaUri;

    /** @var int */
    public $virtualSize;

    private $jsonSchema;

    protected $aliases = [
        'container_format' => 'containerFormat',
        'created_at'       => 'createdAt',
        'disk_format'      => 'diskFormat',
        'updated_at'       => 'updatedAt',
        'min_disk'         => 'minDisk',
        'owner'            => 'ownerId',
        'min_ram'          => 'minRam',
        'virtual_size'     => 'virtualSize',
    ];

    public function populateFromArray(array $data)
    {
        parent::populateFromArray($data);

        $baseUri = $this->getHttpBaseUrl();

        if (isset($data['file'])) {
            $this->fileUri = Utils::appendPath($baseUri, $data['file']);
        }

        if (isset($data['schema'])) {
            $this->schemaUri = Utils::appendPath($baseUri, $data['schema']);
        }
    }

    public function create(array $data)
    {
        $response = $this->execute($this->api->postImages(), $data);
        return $this->populateFromResponse($response);
    }

    public function retrieve()
    {
        $response = $this->executeWithState($this->api->getImage());
        return $this->populateFromResponse($response);
    }

    private function getSchema()
    {
        if (null === $this->jsonSchema) {
            $response = $this->execute($this->api->getImageSchema());
            $this->jsonSchema = new Schema(Utils::jsonDecode($response, false));
        }

        return $this->jsonSchema;
    }

    public function update(array $data)
    {
        // retrieve latest state so we can accurately produce a diff
        $this->retrieve();

        $schema = $this->getSchema();
        $data   = (object) $data;

        // formulate src and des structures
        $des = $schema->normalizeObject($data, $this->aliases);
        $src = $schema->normalizeObject($this, $this->aliases);

        // validate user input
        $schema->validate($des);
        if (!$schema->isValid()) {
            throw new \RuntimeException($schema->getErrorString());
        }

        // formulate diff
        $patch = new JsonPatch();
        $diff = $patch->disableRestrictedPropRemovals($patch->diff($src, $des), $schema->getPropertyPaths());
        $json = json_encode($diff, JSON_UNESCAPED_SLASHES);

        // execute patch operation
        $response = $this->execute($this->api->patchImage(), [
            'id'          => $this->id,
            'patchDoc'    => $json,
            'contentType' => 'application/openstack-images-v2.1-json-patch'
        ]);

        return $this->populateFromResponse($response);
    }

    public function delete()
    {
        $this->executeWithState($this->api->deleteImage());
    }

    public function deactivate()
    {
        $this->executeWithState($this->api->deactivateImage());
    }

    public function reactivate()
    {
        $this->executeWithState($this->api->reactivateImage());
    }

    public function uploadData(StreamInterface $stream)
    {
        $this->execute($this->api->postImageData(), [
            'id'          => $this->id,
            'data'        => $stream,
            'contentType' => 'application/octet-stream',
        ]);
    }

    public function downloadData()
    {
        $response = $this->executeWithState($this->api->getImageData());
        return $response->getBody();
    }

    public function addMember($memberId)
    {
        return $this->model(Member::class, ['imageId' => $this->id, 'id' => $memberId])->create([]);
    }

    public function listMembers()
    {
        return $this->model(Member::class)->enumerate($this->api->getImageMembers(), ['imageId' => $this->id]);
    }

    public function getMember($memberId)
    {
        return $this->model(Member::class, ['imageId' => $this->id, 'id' => $memberId]);
    }
}
