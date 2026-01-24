// types/index.d.ts

// Define types for the application

interface User {
  ID: number;
  RepID: string;
  Name: string;
  br_id: string;
  emailAddress: string;
  join_date: string;
  Actives: string;
}

interface SessionData {
  user: User;
  timestamp: number;
  validationToken: string;
}

interface ActivityLog {
  salesrepTb: number;
  activity_type: string;
  duration: number;
  rDateTime: string;
}

interface RecordingChunk {
  id: number;
  br_id: number;
  imgID: string;
  imgName: string;
  itmName: string;
  type: string;
  user_id: number;
  date: string;
  time: string;
  status: string;
}

// Define the global types for the application
declare global {
  interface Window {
    electronAPI: {
      saveRecording: (buffer: any, filename: string) => Promise<any>;
      getSources: () => Promise<any>;
      onWindowShown: (callback: () => void) => void;
      onWindowHidden: (callback: () => void) => void;
    };
    checkInBtn?: HTMLElement;
    breakBtn?: HTMLElement;
    checkOutBtn?: HTMLElement;
    logoutBtn?: HTMLElement;
    statusText?: HTMLElement;
    activityBadge?: HTMLElement;
    downloadSpeedElement?: HTMLElement;
    uploadSpeedElement?: HTMLElement;
    totalDownloadedElement?: HTMLElement;
    totalUploadedElement?: HTMLElement;
  }
}

export {};