window.config = {
  routerBasename: '/',
  extensions: [],
  modes: [],
  showStudyList: true,

  dataSources: [
    {
      friendlyName: 'DCM4CHEE PACS',
      namespace: '@ohif/extension-default.dataSourcesModule.dicomweb',
      sourceName: 'dicomweb',
      configuration: {
        friendlyName: 'DCM4CHEE PACS',
        name: 'DCM4CHEE',
        wadoUriRoot: 'http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/wado',
        qidoRoot: 'http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/rs',
        wadoRoot: 'http://localhost:8080/dcm4chee-arc/aets/DCM4CHEE/rs',
        qidoSupportsIncludeField: true,
        supportsReject: false,
        imageRendering: 'wadors',
        thumbnailRendering: 'wadors',
        enableStudyLazyLoad: true,
        supportsFuzzyMatching: true,
        supportsWildcard: true,
        staticWado: false,
        singlepart: 'bulkdata,video',
      },
    },
  ],
  defaultDataSourceName: 'dicomweb',

  oidc: [
    {
      authRoot: 'https://localhost:8843/realms/dcm4che',
      clientId: 'ohif-viewer',
      responseType: 'code',
      scope: 'openid profile',
    },
  ],
};
