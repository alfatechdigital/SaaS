<?php

namespace App\Enums;

enum TeamPermission: string
{
    case UpdateTeam = 'team:update';
    case DeleteTeam = 'team:delete';

    case AddMember = 'member:add';
    case UpdateMember = 'member:update';
    case RemoveMember = 'member:remove';

    case CreateInvitation = 'invitation:create';
    case CancelInvitation = 'invitation:cancel';

    // Domain modules — see docs/IMPLEMENTATION_PLAN.md ADR-09.
    case ManageProjects = 'project:manage';
    case ManageContent = 'content:manage';
    case ManageCompanyProfile = 'company-profile:manage';
    case ManagePortfolio = 'portfolio:manage';
    case ManageLeads = 'lead:manage';
    case ManageFinance = 'finance:manage';
    case ViewActivityLog = 'activity-log:view';
}
