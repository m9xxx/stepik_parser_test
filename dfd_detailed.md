# Детализированная диаграмма потоков данных

```mermaid
graph TD
    %% External Entities
    USER[Web Browser]
    STEPIK[Stepik API]
    SKILLBOX[Skillbox API]
    GEEKBRAINS[GeekBrains API]

    %% Processes
    subgraph "API Layer"
        COURSE_CTRL[Course Controller]
        PLATFORM_CTRL[Platform Controller]
        AUTH_CTRL[Auth Controller]
        FAV_CTRL[Favorites Controller]
    end

    subgraph "Service Layer"
        COURSE_SVC[Course Service]
        PARSER_SVC[Parser Service]
    end

    %% Data Stores
    subgraph "Storage"
        DB[(MySQL Database)]
    end

    %% Data Flows - User Interactions
    USER -->|HTTP Requests| COURSE_CTRL
    USER -->|Auth Requests| AUTH_CTRL
    USER -->|Favorite Actions| FAV_CTRL
    USER -->|Platform List| PLATFORM_CTRL
    
    %% Data Flows - API Layer
    COURSE_CTRL -->|Course Operations| COURSE_SVC
    COURSE_CTRL -->|Parser Operations| PARSER_SVC
    
    %% Data Flows - Service Layer
    PARSER_SVC -->|Parse Results| DB
    PARSER_SVC -->|Course Data| COURSE_SVC
    COURSE_SVC -->|Query/Store| DB
    
    %% Data Flows - External APIs
    STEPIK -->|Course Data| PARSER_SVC
    SKILLBOX -->|Course Data| PARSER_SVC
    GEEKBRAINS -->|Course Data| PARSER_SVC
    
    %% Data Flows - Database
    AUTH_CTRL -->|User Data| DB
    FAV_CTRL -->|Favorite Records| DB
    PLATFORM_CTRL -->|Platform Data| DB
    
    %% Response Flows
    COURSE_CTRL -->|JSON Response| USER
    AUTH_CTRL -->|Auth Response| USER
    FAV_CTRL -->|Favorites List| USER
    PLATFORM_CTRL -->|Platforms List| USER

    %% Styles
    classDef external fill:#f9f,stroke:#333,stroke-width:2px;
    classDef process fill:#9f9,stroke:#333,stroke-width:2px;
    classDef storage fill:#99f,stroke:#333,stroke-width:2px;
    
    class USER,STEPIK,SKILLBOX,GEEKBRAINS external;
    class COURSE_CTRL,PLATFORM_CTRL,AUTH_CTRL,FAV_CTRL,COURSE_SVC,PARSER_SVC process;
    class DB,JSON_STORE storage; 