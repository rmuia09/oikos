import APPS from './apps.js'
import Dashboard from '../pages/Dashboard.jsx'
import Properties from '../pages/Properties.jsx'
import Units from '../pages/Units.jsx'
import Tenants from '../pages/Tenants.jsx'
import Onboarding from '../pages/Onboarding.jsx'
import Payments from '../pages/Payments.jsx'
import Reports from '../pages/Reports.jsx'
import Renewals from '../pages/Renewals.jsx'
import Utilities from '../pages/Utilities.jsx'
import Notifications from '../pages/Notifications.jsx'
import Activity from '../pages/Activity.jsx'
import Settings from '../pages/Settings.jsx'
import Users from '../pages/Users.jsx'
import Visitors from '../pages/Visitors.jsx'
import Account from '../pages/Account.jsx'

/* Each app is a window entry. `view` is rendered inside the window body. */

export default [
  /* chromeless: renders as a locked, full-canvas page instead of a window. */
  { id: 'dashboard', title: 'Dashboard', icon: '📊', view: Dashboard, chromeless: true },
  { id: 'properties', title: 'Properties', icon: '🏘️', view: Properties },
  { id: 'units', title: 'Units', icon: '🏠', view: Units },
  { id: 'tenants', title: 'Tenants', icon: '👥', view: Tenants },
  { id: 'onboarding', title: 'Onboarding', icon: '🚪', view: Onboarding },
  { id: 'payments', title: 'Rent Payments', icon: '💰', view: Payments },
  { id: 'reports', title: 'Income Reports', icon: '📈', view: Reports },
  { id: 'renewals', title: 'Lease Renewals', icon: '📅', view: Renewals },
  { id: 'utilities', title: 'Utilities', icon: '💧', view: Utilities },
  { id: 'notifications', title: 'Notifications', icon: '🔔', view: Notifications },
  { id: 'activity', title: 'Activity', icon: '🧾', view: Activity },
  { id: 'settings', title: 'Settings', icon: '⚙️', view: Settings },
  { id: 'users', title: 'Users', icon: '🔐', view: Users, adminOnly: true },
  { id: 'visitors', title: 'Visitors', icon: '👀', view: Visitors, adminOnly: true },
  { id: 'account', title: 'My Account', icon: '🙂', view: Account }
]