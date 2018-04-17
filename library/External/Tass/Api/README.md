## Table of Contents
  1. [Classes](#1-Classes)
  1. [Interfaces](#2-Interfaces)
  1. [Exceptions](#3-Exceptions)

## 1 Classes
### 1.1 ModelFactory
Responsible for mapping raw array data from Tass API into a consistent strongly-typed entity that can be used by Schoolbox.

Insulates the rest of Schoolbox from changes in API response structure.

### 1.2 Connector
Responsible for authentication and connection to the Tass2 HTTP Api


## 2 Interfaces
### 2.1 ITassApiException
Instance of this interface are thrown by the connector for TassAPI-specific reasons.

### 2.2 IResponse
Instance of this interface are produced by the ResponseFactory. They enable consumers of the API to be decoupled from the API implementation.

## 3 Exceptions
Class | Thrown if:
------------- |-------------
InvalidArg  | Method args are invalid
NotFound | Item specified cannot be found
ResponseDecodeException | Cannot decode the API response into an array of data
UdParseException | Cannot parse expected UD data, or unexpected UD data encountered
Unauthorised  | Connection credentials are incorrect
