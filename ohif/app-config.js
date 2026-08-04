window.config = {
  routerBasename: '/',
  showStudyList: true,
  dataSources: [
    {
      friendlyName: 'Orthanc PACS',
      namespace: 'orthanc.pacs',
      sourceName: 'dicomweb',
      configuration: {
        name: 'Orthanc',
        // Ganti localhost dengan IP server Docker bila OHIF diakses dari mesin lain
        // (contoh: workstation PZDR). Lihat README.
        wadoUriRoot: 'http://localhost:8042/wado',
        qidoRoot: 'http://localhost:8042/dicom-web',
        wadoRoot: 'http://localhost:8042/dicom-web',
        qidoSupportsIncludeField: true,
        imageRendering: 'wadors',
        thumbnailRendering: 'wadors',
        enableStudyLazyLoad: true,
        supportsFuzzyMatching: false,
        supportsTagUpdate: true,
        supportsUpdatingSopInstanceMetadata: false,
        singlepart: 'pdf,mp4,json'
      }
    }
  ],
  defaultDataSourceName: 'orthanc.pacs',
  httpErrorHandler: null
};
