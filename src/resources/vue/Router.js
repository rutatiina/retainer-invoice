
const Index = () => import('./components/l-limitless-bs4/Index');
const Form = () => import('./components/l-limitless-bs4/Form');
const Show = () => import('./components/l-limitless-bs4/Show');
const SideBarLeft = () => import('./components/l-limitless-bs4/SideBarLeft');
const SideBarRight = () => import('./components/l-limitless-bs4/SideBarRight');

const routes = [

    {
        path: '/retainer-invoices',
        components: {
            default: Index,
            //'sidebar-left': ComponentSidebarLeft,
            //'sidebar-right': ComponentSidebarRight
        },
        meta: {
            title: 'Accounting :: Sales :: Retainer Invoices',
            metaTags: [
                {
                    name: 'description',
                    content: 'Retainer Invoices'
                },
                {
                    property: 'og:description',
                    content: 'Retainer Invoices'
                }
            ]
        }
    },
    {
        path: '/retainer-invoices/create',
        components: {
            default: Form,
            //'sidebar-left': ComponentSidebarLeft,
            //'sidebar-right': ComponentSidebarRight
        },
        meta: {
            title: 'Accounting :: Sales :: Retainer Invoice :: Create',
            metaTags: [
                {
                    name: 'description',
                    content: 'Create Retainer Invoice'
                },
                {
                    property: 'og:description',
                    content: 'Create Retainer Invoice'
                }
            ]
        }
    },
    {
        path: '/retainer-invoices/:id',
        components: {
            default: Show,
            'sidebar-left': SideBarLeft,
            'sidebar-right': SideBarRight
        },
        meta: {
            title: 'Accounting :: Sales :: Retainer Invoice',
            metaTags: [
                {
                    name: 'description',
                    content: 'Retainer Invoice'
                },
                {
                    property: 'og:description',
                    content: 'Retainer Invoice'
                }
            ]
        }
    },
    {
        path: '/retainer-invoices/:id/copy',
        components: {
            default: Form,
        },
        meta: {
            title: 'Accounting :: Sales :: Retainer Invoice :: Copy',
            metaTags: [
                {
                    name: 'description',
                    content: 'Copy Retainer Invoice'
                },
                {
                    property: 'og:description',
                    content: 'Copy Retainer Invoice'
                }
            ]
        }
    },
    {
        path: '/retainer-invoices/:id/edit',
        components: {
            default: Form,
        },
        meta: {
            title: 'Accounting :: Sales :: Retainer Invoice :: Edit',
            metaTags: [
                {
                    name: 'description',
                    content: 'Edit Retainer Invoice'
                },
                {
                    property: 'og:description',
                    content: 'Edit Retainer Invoice'
                }
            ]
        }
    },

    {
        path: '/estimates/:id/process/retainer-invoices',
        components: {
            default: Form,
        },
        meta: {
            title: 'Accounting :: Estimate :: Process',
            metaTags: [
                {
                    name: 'description',
                    content: 'Process Estimate'
                },
                {
                    property: 'og:description',
                    content: 'Process Estimate'
                }
            ]
        }
    }

]

export default routes
