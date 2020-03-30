import { Middleware } from 'redux';
import { CREATE_MEETING_COMMAND, MEETING_CREATED_EVENT } from './actions';
import { createMeetingService } from './service';
import { push } from 'connected-react-router';

export function createMeetingMiddleware(): Middleware {
    const service = createMeetingService();

    return store => next => action => {
        if (action.type === CREATE_MEETING_COMMAND) {
            service
                .createMeeting(action.meeting)
                .then(meeting => {
                    store.dispatch({
                        type: MEETING_CREATED_EVENT,
                        meeting
                    });
                })
                .catch(error => {
                    console.error('Create meeting failed: ', error);
                    store.dispatch(push('/error'));
                });
        }

        if (action.type === MEETING_CREATED_EVENT) {

            /* We store a schoolbox instance's domain as a url param
             * We use it to redirect to another location in that domain where
             * we can dump out the meeting's url
             */
            const url = new URL(document.location.href);
            let returnUrl = new URL(url.searchParams.get('instance') + '/api/teams.php');

            /* The meeting's url is also another querystring param
             * These two pieces of the puzzle are then visited and echo'd
             * so that we can pass the message up the schoolbox instance's iframe hierarchy
             */
            let returnUrlSearchParams = returnUrl.searchParams;
            returnUrlSearchParams.set('meetingUrl', action.meeting.joinWebUrl);

            returnUrl.search = returnUrlSearchParams.toString();

            document.location.href = returnUrl.toString();

            /* What is dead may never die
             * (This is what was previously here)
             */
            // store.dispatch(push('/copyMeeting'));
        }
        next(action);
    };
}
