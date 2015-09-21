<?php

/**
 * Class ServiceNotFoundException
 */
class ServiceNotFoundException extends Exception {}

/**
 * Class ThirdPartyService generic superclass for third-party services
 */
abstract class Application_Service_ThirdPartyService {

    /**
     * @var string service name to store in ThirdPartyTrackReferences database
     */
    protected static $_SERVICE_NAME;

    /**
     * Create a ThirdPartyTrackReferences object for a track that's been uploaded
     * to an external service
     * TODO: should we have a database layer class to handle Propel operations?
     *
     * @param $fileId int local CcFiles identifier
     *
     * @return string the new ThirdPartyTrackReferences identifier
     *
     * @throws Exception
     * @throws PropelException
     */
    public function createTrackReference($fileId) {
        // First, check if the track already has an entry in the database
        $ref = ThirdPartyTrackReferencesQuery::create()
            ->filterByDbService(static::$_SERVICE_NAME)
            ->findOneByDbFileId($fileId);
        if (is_null($ref)) {
            $ref = new ThirdPartyTrackReferences();
        }
        $ref->setDbService(static::$_SERVICE_NAME);
        // TODO: implement service-specific statuses?
        // $ref->setDbStatus(CELERY_PENDING_STATUS);
        $ref->setDbFileId($fileId);
        $ref->save();
        return $ref->getDbId();
    }

    /**
     * Remove a ThirdPartyTrackReferences row from the database.
     * This is necessary if the track was removed from the service
     * or the foreign id in our database is incorrect
     *
     * @param $fileId int cc_files identifier
     *
     * @throws Exception
     * @throws PropelException
     */
    public function removeTrackReference($fileId) {
        $ref = ThirdPartyTrackReferencesQuery::create()
            ->filterByDbService(static::$_SERVICE_NAME)
            ->findOneByDbFileId($fileId);
        $ref->delete();
    }

    /**
     * Given a CcFiles identifier for a file that's been uploaded to a third-party service,
     * return the third-party identifier for the remote file
     *
     * @param int $fileId the cc_files identifier
     *
     * @return string the service foreign identifier
     */
    public function getServiceId($fileId) {
        $ref = ThirdPartyTrackReferencesQuery::create()
            ->filterByDbService(static::$_SERVICE_NAME)
            ->findOneByDbFileId($fileId);  // There shouldn't be duplicates!
        return empty($ref) ? '' : $ref->getDbForeignId();
    }

    /**
     * Check if a reference exists for a given CcFiles identifier
     *
     * @param int $fileId the cc_files identifier
     *
     * @return string the service foreign identifier
     */
    public function referenceExists($fileId) {
        $ref = ThirdPartyTrackReferencesQuery::create()
            ->filterByDbService(static::$_SERVICE_NAME)
            ->findOneByDbFileId($fileId);  // There shouldn't be duplicates!
        if (!empty($ref)) {
            $task = CeleryTasksQuery::create()
                ->findOneByDbTrackReference($ref->getDbId());
            return $task->getDbStatus() != CELERY_FAILED_STATUS;
        }
        return false;
    }

}