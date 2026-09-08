/**
 * Control Panel entry. The registered names must match what the controllers
 * pass to `Inertia::render()`, exactly — a mismatch is a blank screen with
 * nothing in the log.
 */

import AssessmentsIndex from './pages/Assessments/Index.vue';
import AssessmentsEdit from './pages/Assessments/Edit.vue';
import AssessmentsResponses from './pages/Assessments/Responses.vue';
import SetupRequired from './pages/SetupRequired.vue';

Statamic.booting(() => {
    Statamic.$inertia.register('assessments::Assessments/Index', AssessmentsIndex);
    Statamic.$inertia.register('assessments::Assessments/Edit', AssessmentsEdit);
    Statamic.$inertia.register('assessments::Assessments/Responses', AssessmentsResponses);
    Statamic.$inertia.register('assessments::SetupRequired', SetupRequired);
});
