ALTER TABLE `ai_moderation_event`
MODIFY COLUMN `status` enum(
    'no_behaviour_flagged',
    'requires_attention',
    'no_action_required',
    'further_action_required',
    'further_action_taken'
) NOT NULL;
