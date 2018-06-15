<?php
namespace app\core\events;

use app\models\ProposalsStatuses;

class EProposal{

    public function onChangeStatus($event)
    {
        $proposal = $event->sender;
        switch ($proposal->status_id){
            case ProposalsStatuses::PENDING_STATUS_ID:
                break;
            case ProposalsStatuses::ACCEPTED_STATUS_ID:
                //Шлем Push
                $proposal->worksheet->user->sendPush('Ваша заявка одобрена', $proposal->mfo->name);
                break;
            case ProposalsStatuses::DECLINE_STATUS_ID:
                break;
            default:
        }
    }

}